<?php

namespace App\Http\Controllers\SynchronousRefund;

use App\Http\Controllers\Controller;
use App\Libraries\GuzzleLibrary;
use App\Models\AdminModel;
use App\Models\OrderModel;
use App\Utilities\PGCompanyUtility;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

/**
 * Check synchronous cancellation flow here : https://developers.cafe24.com/app/front/refer/apppg/paymentprocess/asynccancelpay
 * 1. Cafe24 -> PG app (cancelPayment)
 * 2. PG app (cancelPayment) -> PG Company for refund request
 * 3. PG app (cancelPayment) sends update -> Cafe24 via cancellation NOTY url
 * @package App\Http\Controllers\SynchronousRefund
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
     * This method will handle cancellation request from Cafe24.
     * After sending the request to the PG Company, this method will send the updates to Cafe24 via their cancellation noty url.
     * See info about request and return here : https://developers.cafe24.com/app/front/refer/apppg/pgapireference/cancelpayment
     * Read about notifying Cafe24 here : https://developers.cafe24.com/app/front/refer/apppg/pgapireference/notifycancelpayment
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancelPayment()
    {
        $aRequest = $this->oRequest->all();

        // Validate request for required params
        if (isset($aRequest['order_id']) === false || isset($aRequest['amount']) === false ||isset($aRequest['partner_id']) === false || $aRequest['order_id'] === null || $aRequest['amount'] === null || $aRequest['partner_id'] === null) {
            return Response::json([
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
        $oOrderModel = new OrderModel('sync-refund');
        $mOrder = $oOrderModel->getOrder($aRequest['order_id']);
        if($mOrder === null) {
            return Response::json(
                [
                    'result_code'    => '9999',
                    'result_message' => 'Order not found'
                ],404);
        }

        // Prepare data for PG cancellation request
        $aRefundRequest = [
            'merchant_reference_no' => $mOrder['order_id'],
            'reference_no'  => $mOrder['pg_order_reference_no'],
            'refund_amount' => $aRequest['cancel_amount'],
            'currency'      => $aRequest['currency']
        ];

        // Initialize PG Company's http client
        $oPGUtil = new PGCompanyUtility('synchronous-refund');
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
            'refunded_amount'        => $aRefund['mData']['refunded_amount'],
            'pg_refund_reference_no' => $aRefund['mData']['refund_code'],
            'refund_status'          => $aRefund['mData']['refund_status'],
            'cancel_noty_url'        => $aRequest['cancel_noty_url']
        ];
        $oOrderModel->updateOrder($mOrder['order_id'], $aOrderUpdate);

        // Once Cafe24 receives the '0000' result code, the result of the cancellation will reflect on the Cafe24 EC Admin's order dashboard
        return Response::json([
            'result_code'    => '0000',
            'result_message' => 'Refund success'
        ], 200);
    }
}
