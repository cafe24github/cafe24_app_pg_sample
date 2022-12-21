<?php

namespace App\Http\Controllers\AsynchronousCheckout;

use App\Http\Controllers\Controller;
use App\Libraries\GuzzleLibrary;
use App\Models\AdminModel;
use App\Models\OrderModel;
use App\Utilities\PGCompanyUtility;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

/**
 * Read more about the asynchronous checkout flow here: https://developers.cafe24.com/app/front/refer/apppg/paymentprocess/asyncapprovepay
 * 1. Cafe24 -> PG app (createCheckout), returns payment url
 * 2. Cafe24 redirects to payment url
 * 3. Buyer interacts with PG Company's payment page
 * 4. PG Company's payment page -> PG app (handleCallback)
 * 5. PG app (handleCallback) redirects to the return url
 * 6. PG Company sends updates -> PG app (handleWebhook)
 * 7. PG app (handleWebhook) sends update -> Cafe24 NOTY url
 * @package App\Http\Controllers\AsynchronousCheckout
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
     * Receives the checkout request and calls the PG Company to request for a payment URL
     * Cafe24 will call this endpoint -> PG app will respond with the payment URL
     * Return : payment URL
     * Cafe24 Payment Request API : https://developers.cafe24.com/app/front/refer/apppg/pgapireference/bookingpayment
     * @return \Illuminate\Http\JsonResponse
     */
    public function createCheckout()
    {
        $aRequest = $this->oRequest->all();

        // Validate checkout data by comparing the hash data of the request and the data it came with
        // See hash data pattern in the Cafe24 Payment Request API docs
        $sCheckoutHash = $aRequest['hash_data'];
        $aHashContents = [
            'amount'     => $aRequest['amount'],
            'currency'   => $aRequest['currency'],
            'order_id'   => $aRequest['order_id'],
            'partner_id' => $aRequest['partner_id']
        ];
        // The APP_SERVICE_KEY is provided in the Cafe24 Developer Center once the PG app is created
        $bValidHashData = $sCheckoutHash === base64_encode(hash_hmac('sha256', implode('', $aHashContents), env('APP_SERVICE_KEY'), true));
        if ($bValidHashData === false) {
            return Response::json(
                [
                    'result_code'    => '9999',
                    'result_message' => 'Invalid Hmac',
                    'payment_url'    => ''
                ],
                400
            );
        }

        // Get the mall id from the partner id; the mall id is going to be used to fetch the admin settings.
        // The partner id was provided to Cafe24 in AdminController@toggleShops -> Cafe24Utility@enablePGOnShop
        $aPartnerIdData = explode(':', $aRequest['partner_id']); // $aPartnerIdData = array('ectmtjpq001', '1', 'demo-app-public-key')
        $sMallId = $aPartnerIdData[0];
        $iShopIndex = $aPartnerIdData[1];

        // Get admin settings
        $mAdminSettings = AdminModel::getAdmin($sMallId);
        if ($mAdminSettings === null) {
                return Response::json(
                    [
                        'result_code'    => '9999',
                        'result_message' => 'Mall does not exist',
                        'payment_url'    => ''
                    ],
                    400
                );
        }

        // Check admin settings if payment is enabled (in the Paymentgateway API)
        $bAdminPgEnabled = $mAdminSettings['pg_connected'] === true && $mAdminSettings['shops'][$iShopIndex]['pg_enabled'] === true;
        if ($bAdminPgEnabled === false) {
            return Response::json(
                [
                    'result_code'    => '9999',
                    'result_message' => 'Payment gateway is not enabled on this shop',
                    'payment_url'    => ''
                ],
                400
            );
        }

        // Store order data
        $aOrder = [
            'order_id'                => $aRequest['order_id'],
            'mall_name'               => $mAdminSettings['mall_name'],
            'partner_id'              => $aRequest['partner_id'],
            'buyer_id'                => $aRequest['buyer_id'],
            'shop_no'                 => $aRequest['shop_no'],
            'order_status'            => 'requested',
            'checkout_request_amount' => $aRequest['amount'],
            'pg_order_reference_no'   => null,
            'pg_refund_reference_no'  => null,
            'paid_amount'             => null,
            'refund_amount'           => null,
            'return_url'              => $aRequest['return_url'],
            'return_noty_url'         => $aRequest['return_noty_url']
        ];
        $oOrderModel = new OrderModel('async-checkout');
        $oOrderModel->storeOrder($aRequest['order_id'], $aOrder);

        // Initialize PG Company's http client
        $oPGCompanyUtil = new PGCompanyUtility('asynchronous-checkout');
        $oPGCompanyUtil->setPGClient(['public_key' => $mAdminSettings['pg_public_key'], 'secret_key' => $mAdminSettings['pg_secret_key']]);

        // Prepare data for PG checkout request
        $aPGCheckoutRequest = [
            // PG Companies typically provide the developers to include a reference in their checkout request.
            // The company will then give back the reference to their callback or webhook requests to the PG app.
            'reference_no' => $aRequest['order_id'],
            'amount'       => $aRequest['amount'],
            'currency'     => $aRequest['currency'],
            'callback_url' => env('APP_URL') . '/api/asynchronous-checkout/callback',
            'webhook_url'  => env('APP_URL') . '/api/asynchronous-checkout/webhook'
        ];

        // Request checkout to PG Company
        $aCheckoutResult = $oPGCompanyUtil->createOrderCheckout($aPGCheckoutRequest);
        if (array_key_exists('mData', $aCheckoutResult) === false) {
            return Response::json(
                [
                    'result_code'    => '9999',
                    'result_message' => 'Something went wrong in requesting a checkout',
                    'payment_url'    => ''
                ],
                400
            );
        }
        if ($aCheckoutResult['mData']['code'] !== 200) {
            return Response::json(
                [
                    'result_code'    => '9999',
                    'result_message' => 'Something went wrong in requesting a checkout',
                    'payment_url'    => ''
                ],
                400
            );
        }

        // The payment URL will be given to Cafe24 which they will redirect the buyer to
        return Response::json(
            [
                'result_code'    => '0000',
                'result_message' => 'Successfully created checkout',
                'payment_url'    => $aCheckoutResult['mData']['redirect_uri']
            ],
            200
        );
    }

    /**
     * Receive callback request from PG company after the buyer approves the payment
     * PG Company will call this endpoint -> PG App will redirect to Cafe24
     * Return : redirect to the return url from Cafe24
     * See about return here : https://developers.cafe24.com/app/front/refer/apppg/pgapireference/checkpaymentasync
     * @return \Illuminate\Http\RedirectResponse
     */
    public function handleCallback()
    {
        $aRequest = $this->oRequest->all();

        // Validate request for required params
        if (isset($aRequest['reference_no']) === false || $aRequest['reference_no'] === null) {
            abort(400, 'Invalid request');
        }

        // Get stored order data
        $oOrderModel = new OrderModel('async-checkout');
        $aOrder = $oOrderModel->getOrder('callback-' . $aRequest['reference_no']);
        if ($aOrder === null) {
            abort(404, 'Order not found');
        }

        if ($aRequest['status'] === 'P' || $aRequest['status'] === 'S') {
            $aParams = array(
                'result_code'    => '0000',
                'result_message' => 'Success',
                'extra_data'     => $aOrder['extra_data']
            );
        } else if ($aRequest['status'] === 'F') {
            $aParams = array(
                'result_code'    => '0001',
                'result_message' => 'Failed',
                'extra_data'     => $aOrder['extra_data']
            );
        } else {
            $aParams = array(
                'result_code'    => '9999',
                'result_message' => 'Cancelled',
                'extra_data'     => $aOrder['extra_data']
            );
        }

        // The callback will perform a redirect to the Cafe24 return url ("/Pay/Recv/openpg/PayReceiveRtn.php") to notify them
        // Afterwards, Cafe24 will redirect the buyer back to the EC mall while waiting for the payment notification from the PG Company
        $sReturnUrl = $aOrder['return_url'] . '?' . http_build_query($aParams);

        echo 'Redirecting to: ' . $sReturnUrl;
        return;

        return redirect()->secure($sReturnUrl);
    }

    /**
     * When the PG Company have updates about the payment, they can send the notification to this method
     * PG app then sends updates to Cafe24 through the return_noty_url (endpoint for notifying Cafe24 about order status)
     * return_noty_url specifications : https://developers.cafe24.com/app/front/refer/apppg/pgapireference/successpayment
     * Return : Depending on the PG Company's requirements
     * PG Company -> PG app -> Cafe24
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleWebhook()
    {
        $aRequest = $this->oRequest->all();

        // Validate request for required params
        if (array_key_exists('reference_id', $aRequest) === false || array_key_exists('order_code', $aRequest) === false || array_key_exists('status', $aRequest) === false || array_key_exists('amount', $aRequest) === false || array_key_exists('currency', $aRequest) === false) {
            // Return to PG company according to PG company return specifications
            return Response::json(['message' => 'Invalid request'], 400);
        }

        // Get stored order data
        $oOrderModel = new OrderModel('async-checkout');
        $aOrder = $oOrderModel->getOrder('webhook-' . $aRequest['reference_id']);
        if ($aOrder === null) {
            return Response::json(['message' => 'Invalid request'], 400);
        }

        // Get admin data for the merchant credentials (public_key and secret_key)
        $aAdmin = AdminModel::getAdmin($aOrder['mall_name']);
        if ($aAdmin === null) {
            return Response::json(['message' => 'Invalid request'], 400);
        }

        // Validate the request data by checking digest
        // The digest pattern will depend on the PG Company's specifications
        $sRequestDigest = $aRequest['digest'];
        $sCreatedDigest = sha1(implode(':', [
            $aRequest['reference_id'],
            $aRequest['order_code'],
            $aRequest['status'],
            $aRequest['amount'],
            $aRequest['currency'],
            $aAdmin['pg_secret_key']
        ]));
        if ($sRequestDigest !== $sCreatedDigest) {
            return Response::json(['message' => 'Invalid request'], 400);
        }

        // Prepare data for notifying Cafe24 asynchronously
        $sOrderStatus = PGCompanyUtility::PG_STATUS_TO_APP_STATUS_MAPPING[$aRequest['status']];
        $sAmount = $sOrderStatus === 'paid' ? $aRequest['amount']: '0';
        $sPaidStaus = $sOrderStatus === 'paid' ? 'T' : 'F';
        $sKey = $sAmount . $aRequest['currency'] . $aRequest['order_code'] . $aOrder['partner_id'] . $aRequest['order_code'];
        // The APP_SERVICE_KEY is provided in the Cafe24 Developer Center once the PG app is created
        $sHash = base64_encode(hash_hmac('sha256', $sKey, env('APP_SERVICE_KEY'), true));
        $aTestExtraData = ['pgName' => 'PG-Demo-app'];
        $sResultCode = $sOrderStatus === 'paid' ? '0000' : '9999';
        $sMessage = $aRequest['message'];

        try {
            // Send request to the Cafe24 return_noty_url with Guzzle
            $oGuzzle = new GuzzleLibrary(new \GuzzleHttp\Client());
            $aRequestParams = [
                'headers' => ['Content-Type' => 'application/json charset-utf-8'],
                'form_params' => [
                    'request_type'   => 'payment',
                    'partner_id'     => $aOrder['partner_id'],
                    // Check the list of Payment Codes in the Cafe24 docs : https://developers.cafe24.com/app/front/refer/apppg/integrationcode/basiccode
                    // Confirm with the Cafe24 representative to know what the value of paymethod should be for your PG Company
                    'paymethod'      => 'etc',
                    'tid'            => $aRequest['order_code'],
                    'amount'         => $sAmount,
                    'order_id'       => $aOrder['order_id'],
                    'all_cancel_tf'  => 'T',
                    'part_cancel_tf' => 'T',
                    'escrow_tf'      => 'F',
                    'currency'       => $aRequest['currency'],
                    'payed_tf'       => $sPaidStaus,
                    'easypay'        => 'F',
                    'hash_data'      => $sHash,
                    'extra_data'     => $aTestExtraData,
                    'result_code'    => $sResultCode,
                    'result_message' => $sMessage
                ]
            ];

            // Send the request to Cafe24
            // $aCafe24NotyResult = $oGuzzle->request('POST', $aOrder['return_noty_url'], $aRequestParams);

            // For the sake of the Postman demo request, the Guzzle result is going to be mocked.
            $aCafe24NotyResult = ['mData' => '{"result" : "OK"}'];
        } catch (GuzzleException $oError) {
            return Response::json(['message' => 'Failed webhook'], 400);
        }

        if (json_decode($aCafe24NotyResult['mData'], true)['result'] !== 'OK') {
            // Update order data in PG app's database if notification failed
            $aOrderUpdate = [
                'order_status'          => 'Failed',
                'pg_order_reference_no' => $aRequest['order_code'],
                'paid_amount'           => '0.00'
            ];
            $oOrderModel->updateOrder($aOrder['order_id'], $aOrderUpdate);

            return Response::json(['message' => 'Failed webhook'], 400);
        }

        // Update the order data in the PG app's database once the notification to Cafe24 is successful
        $aOrderUpdate = [
            'order_status'          => $sOrderStatus,
            'pg_order_reference_no' => $aRequest['order_code'],
            'paid_amount'           => $sAmount
        ];
        $oOrderModel->updateOrder($aOrder['order_id'], $aOrderUpdate);

        // Once Cafe24 receives the notification, the result of the payment will reflect on Cafe24 EC Admin's order dashboard
        return Response::json(['message' => 'Success webhook'], 200);
    }
}
