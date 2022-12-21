<?php

namespace App\Http\Controllers\AsynchronousRefund;

use App\Http\Controllers\Controller;
use App\Libraries\GuzzleLibrary;
use App\Models\AdminModel;
use App\Models\OrderModel;
use App\Utilities\PGCompanyUtility;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

/**
 * Check asynchronous cancellation flow here : https://developers.cafe24.com/app/front/refer/apppg/paymentprocess/asynccancelpay
 * 1. Cafe24 -> PG app (cancelPayment)
 * 2. PG app (cancelPayment) -> PG Company for refund
 * 3. PG Company sends update -> PG app (handleCancellationWebhook)
 * 4. PG app (handleCancellationWebhook) sends update -> Cafe24 via cancellation NOTY url
 * @package App\Http\Controllers\AsynchronousRefund
 */
class InternalController extends Controller
{
    /**
     * @var Request
     */
    private $oRequest;

    public function __construct(Request $oRequest)
    {
        $this->oRequest = $oRequest;
    }

    /**
     * This method will handle the cancellation request from Cafe24.
     * Cafe24 -> PG app -> PG company
     * See info about request and return here : https://developers.cafe24.com/app/front/refer/apppg/pgapireference/cancelpayment
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancelPayment()
    {
        $aRequest = $this->oRequest->all();

        // Validate request for required params
        if (isset($aRequest['order_id']) === false || isset($aRequest['amount']) === false ||isset($aRequest['partner_id']) === false || $aRequest['order_id'] === null || $aRequest['amount'] === null || $aRequest['partner_id'] === null) {
            return Response::json(
                [
                    'result_code'    => '9999',
                    'result_message' => 'Invalid request'
                ],400);
        }

        // Validate hash_data
        $sRequestHashData = $aRequest['hash_data'];
        $sKey = $aRequest['cancel_amount'] . $aRequest['currency'] . $aRequest['order_id'] . $aRequest['partner_id'] . $aRequest['tid'];
        // The APP_SERVICE_KEY is provided in the Cafe24 Developer Center once the PG app is created
        $sCreatedHashData = base64_encode(hash_hmac('sha256', $sKey, env('APP_SERVICE_KEY'), true));
        if($sRequestHashData !== $sCreatedHashData) {
            return Response::json(
                [
                    'result_code'    => '9999',
                    'result_message' => 'Invalid request'
                ],400);
        }

        // Get the mall id from the partner id; the mall id is going to be used to fetch the admin settings.
        // The partner id was provided to Cafe24 in AdminController@toggleShops -> Cafe24Utility@enablePGOnShop
        $sMallId = explode(':', $aRequest['partner_id'])[0];

        // Get admin settings
        $mAdmin = AdminModel::getAdmin($sMallId);
        if($mAdmin === null) {
            return Response::json(
                [
                    'result_code'    => '9999',
                    'result_message' => 'Admin setting not found'
                ],404);
        }

        // Get order data
        $oOrderModel = new OrderModel('async-refund');
        $mOrder = $oOrderModel->getOrder('cancel-' . $aRequest['order_id']);
        if($mOrder === null) {
            return Response::json(
                [
                    'result_code'    => '9999',
                    'result_message' => 'Order not found'
                ],404);
        }

        // Prepare data for PG cancellation request
        $aRefundRequest = [
            // PG Companies typically provide the developers to include a reference in their checkout request.
            // The company will then give back the reference to their callback or webhook requests to the PG app.
            'reference_no' => $mOrder['order_id'],
            'order_code'  => $mOrder['pg_order_reference_no'],
            'refund_amount' => $aRequest['cancel_amount'],
            'currency'      => $aRequest['currency']
        ];

        // Initialize PG Company's http client
        $oPGUtil = new PGCompanyUtility('asynchronous-refund');
        $oPGUtil->setPGClient(['public_key' => $mAdmin['pg_public_key'], 'secret_key' => $mAdmin['pg_secret_key']]);

        // Send cancellation request to the PG Company
        $aRefund = $oPGUtil->createRefund($mOrder['pg_order_reference_no'], $aRefundRequest);
        if (array_key_exists('mData', $aRefund) === false) {
            return Response::json(
                [
                    'result_code'    => '9999',
                    'result_message' => 'Refund request failed'
                ],404);
        }

        // Update stored order
        $aOrderUpdate = [
            'request_refund_amount'  => $aRefund['mData']['request_refund_amount'],
            'pg_refund_reference_no' => $aRefund['mData']['refund_code'],
            'refund_status'          => $aRefund['mData']['refund_status'],
            'cancel_noty_url'        => $aRequest['cancel_noty_url']
        ];
        $oOrderModel->updateOrder($mOrder['order_id'], $aOrderUpdate);

        // Once Cafe24 receives the notification, the result of the cancellation will reflect on the Cafe24 EC Admin's order dashboard
        return Response::json(
            [
                'result_code'    => '0000',
                'result_message' => 'Refund requested'
            ],200);
    }

    /**
     * When the PG company have updates about the refund request, this method will handle the notification request
     * This method will then decide if Cafe24 needs to be notified
     * PG company -> PG app
     * Read about notifying Cafe24 here : https://developers.cafe24.com/app/front/refer/apppg/pgapireference/notifycancelpayment
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleCancellationWebhook()
    {
        $aRequest = $this->oRequest->all();

        // Validate required params
        if(isset($aRequest['reference_no']) === false || $aRequest['reference_no'] === null) {
            return Response::json(
                [
                    'result_code'    => 400,
                    'result_message' => 'Invalid request'
                ],400);
        }

        // Get stored order data
        $oOrderModel = new OrderModel('async-refund');
        $mOrder = $oOrderModel->getOrder('webhook-' . $aRequest['reference_no']);
        if($mOrder === null) {
            return Response::json(
                [
                    'result_code'    => 404,
                    'result_message' => 'Order not found'
                ],404);
        }

        // If refund status is still pending there is no need updating order in store and will wait for the next webhook request from the PG Company
        if ($aRequest['refund_status'] === 'RP') {
            return Response::json(['code' => 200, 'message' => 'Refund still pending'],200);
        }

        // Prepare data for notifying Cafe24 asynchronously
        $sUrl = $mOrder['cancel_noty_url'];
        $sPartnerId = $mOrder['partner_id'];
        $sCancelAmount = $mOrder['refund_amount'] === null ? '0.00' : $mOrder['refund_amount'];
        $sCurrency = $mOrder['currency'];
        $sOrderId = $mOrder['order_id'];
        $sTransactionId = $mOrder['pg_order_reference_no'];
        $sMessage = $aRequest['message'];
        $sPayMethod = 'etc';
        if ($aRequest['refund_status'] === 'R') {
            $sResultCode = '0000';
            $sRefundStatus = 'P'; // Refund completed (successful processing)
        }
        if ($aRequest['refund_status'] === 'RP') {
            $sResultCode = '9999';
            $sRefundStatus = 'P'; // Refund completed (successful processing)
        }
        if ($aRequest['refund_status'] === 'RX') {
            $sResultCode = '9999';
            $sRefundStatus = 'F'; // Refusal of refund request (cancellation failed)
        }
        $sKey = $sCancelAmount.$sCurrency.$sOrderId.$sPartnerId.$sTransactionId;
        $sHash = base64_encode(hash_hmac('sha256', $sKey, env('APP_SERVICE_KEY'), true));

        try {
            // Send request to the Cafe24 cancel_noty_url with Guzzle
            $oGuzzle = new GuzzleLibrary(new \GuzzleHttp\Client());
            $aRequestParams = [
                'headers' => ['Content-Type' => 'application/json charset-utf-8'],
                'form_params' => [
                    'request_type'      => 'cancelnoty',
                    'partner_id'        => $sPartnerId,
                    'tid'               => $sTransactionId,
                    'order_id'          => $sOrderId,
                    'paymethod'         => $sPayMethod,
                    'currency'          => $sCurrency,
                    'cancel_amount'     => $sCancelAmount,
                    'status'            => $sRefundStatus,
                    'hash_data'         => $sHash,
                    'extra_data'        => $mOrder['extra_data'],
                    'result_code'       => $sResultCode,
                    'result_message'    => $sMessage,
                ]
            ];

            // Send the request to Cafe24
            // $aCafe24NotyResult = $oGuzzle->request('POST', $sUrl, $aRequestParams);

            // For the sake of the Postman demo request, the Guzzle result is going to be mocked.
            $aCafe24NotyResult = ['mData' => '{"result" : "OK"}'];
        } catch (GuzzleException $oError) {
            return Response::json(
                [
                    'code'    => 400,
                    'message' => 'Failed to notify Cafe24'
                ],400);
        }

        if (json_decode($aCafe24NotyResult['mData'], true)['result'] !== 'OK') {
            return Response::json(['code' => 400,'message' => 'Failed to notify Cafe24'],400);
        }

        // Update the order data in the PG app's database once the notification to Cafe24 is successful
        $aOrderUpdate = [
            'order_status'  => $aRequest['refund_status'] === 'R' ? 'refunded' : 'paid',
            'refund_status' => PGCompanyUtility::PG_STATUS_TO_APP_STATUS_MAPPING[$aRequest['refund_status']],
            'refund_amount' => $aRequest['refund_status'] === 'R' ? $aRequest['refund_amount'] : NULL,
        ];
        $oOrderModel->updateOrder($aRequest['reference_no'], $aOrderUpdate);

        // Once Cafe24 receives the notification, the result of the cancellation will reflect on the Cafe24 EC Admin's order dashboard
        return Response::json(['code' => 200, 'message' => 'Refund success'], 200);
    }
}
