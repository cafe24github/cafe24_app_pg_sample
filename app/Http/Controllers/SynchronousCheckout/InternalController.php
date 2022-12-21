<?php

namespace App\Http\Controllers\SynchronousCheckout;

use App\Http\Controllers\Controller;
use App\Models\AdminModel;
use App\Models\OrderModel;
use App\Utilities\PGCompanyUtility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

/**
 * Read more about the synchronous checkout flow here: https://developers.cafe24.com/app/front/refer/apppg/paymentprocess/syncapprovepay
 * 1. Cafe24 -> PG app (createCheckout), returns payment url
 * 2. Cafe24 redirects to payment url
 * 3. Buyer interacts with PG Company's payment page
 * 4. PG Company's payment page -> PG app (handleCallback)
 * 5. PG app (handleCallback) updates status of the stored order
 * 6. PG app (handleCallback) redirects to return url
 * 7. Cafe24 requests to read order -> PG app (getPaymentStatus)
 * @package App\Http\Controllers\SynchronousCheckout
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
        $bValidHashData = $sCheckoutHash === base64_encode(hash_hmac('sha256',  implode('', $aHashContents), env('APP_SERVICE_KEY'), true));
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
        // The partner id was provided to the Cafe24 in AdminController@toggleShops -> Cafe24Utility@enablePGOnShop
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
            'order_status'            => 'pending',
            'checkout_request_amount' => $aRequest['amount'],
            'return_url'              => $aRequest['return_url'],
            'pg_order_reference_no'   => null,
            'pg_refund_reference_no'  => null,
            'paid_amount'             => '0.00',
            'refund_amount'           => null,
        ];
        $oOrderModel = new OrderModel('sync-checkout');
        $sOrderKey = $oOrderModel->storeOrder($aRequest['order_id'], $aOrder);

        // Initialize PG Company's http client
        $oPGCompanyUtil = new PGCompanyUtility('synchronous-checkout');
        $oPGCompanyUtil->setPGClient(['public_key' => $mAdminSettings['pg_public_key'], 'secret_key' => $mAdminSettings['pg_secret_key']]);

        // Prepare data for PG checkout request
        $aPGCheckoutRequest = [
            // PG Companies typically provide the developers to include a reference in their checkout request.
            // The company will then give back the reference to their callback or webhook requests to the PG app.
            'reference_no' => $aRequest['order_id'],
            'amount'       => $aRequest['amount'],
            'currency'     => $aRequest['currency'],
            // The "key" parameter is used in the callback response that will be provided to the Cafe24 return url
            // The PG Company will typically append parameters from their end to the supplied callback URL, so expect more than 1 request parameter on the callback
            'callback_url' => env('APP_URL') . '/api/synchronous-checkout/callback' . http_build_query(['key' => $sOrderKey])
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

        // The payment URL will be given to the Cafe24 which they will redirect the buyer to
        return Response::json(
            [
                'result_code'    => '0000',
                'result_message' => 'Successfully created checkout',
                // The 'key' URL parameter should be hashed. It will be used to validate the response from calling '/Pay/Recv/openpg/PayReceiveRtn.php' during the synchronous payment results process
                // Reference: https://developers.cafe24.com/app/front/refer/apppg/pgapireference/checkpaymentsync
                'payment_url'    => $aCheckoutResult['mData']['redirect_uri'] . '?key=' . $sOrderKey
            ],
            200
        );
    }

    /**
     * Receive callback request from PG company after the buyer approves the payment
     * PG Company will call this endpoint -> PG App will redirect to Cafe24
     * Return : redirect url to show payment result
     * See about return here : https://developers.cafe24.com/app/front/refer/apppg/pgapireference/checkpaymentsync
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
        $oOrderModel = new OrderModel('sync-checkout');
        $mOrder = $oOrderModel->getOrder('checkout-' . $aRequest['reference_no']);
        if ($mOrder === null) {
            abort(404, 'Order not found');
        }

        // Get admin data for the merchant credentials (public_key and secret_key)
        $mAdmin = AdminModel::getAdmin($mOrder['mall_name']);
        if ($mAdmin === null) {
            $aCafe24Return = [
                'result_code'    => 9999,
                'result_message' => 'Admin not found',
                'key'            => $aRequest['key'],
                'extra_data'     => json_encode($mOrder['extra_data'])
            ];
            $sReturnUrl = $mOrder['return_url'] . http_build_query($aCafe24Return);

            // For the sake of the Postman demo request, the function will be terminated here
            echo 'Redirecting to: ' . $sReturnUrl;
            return;

            return redirect()->secure($sReturnUrl);
        }

        // Get order info from the PG Company to validate the callback request
        $oPGCompanyUtil = new PGCompanyUtility('synchronous-checkout');
        $oPGCompanyUtil->setPGClient(['public_key' => $mAdmin['pg_public_key'], 'secret_key' => $mAdmin['pg_secret_key']]);
        $aPGOrder = $oPGCompanyUtil->getOrder($aRequest['reference_no']);
        if (array_key_exists('mData', $aPGOrder) === false) {
            $aCafe24Return = [
                'result_code'    => 9999,
                'result_message' => 'PG order not found',
                'key'            => $aRequest['key'],
                'extra_data'     => json_encode($mOrder['extra_data'])
            ];
            $sReturnUrl = $mOrder['return_url'] . http_build_query($aCafe24Return);

            // For the sake of the Postman demo request, the function will be terminated here
            echo 'Redirecting to: ' . $sReturnUrl;
            return;

            return redirect()->secure($sReturnUrl);
        }

        // Update Cafe24 Cafe24 if payment is successful
        if ($aPGOrder['mData']['order_status'] === 'S') {
            $aOrderUpdate = [
                'pg_order_reference_no' => $aRequest['order_code'],
                'order_status'          => PGCompanyUtility::PG_STATUS_TO_APP_STATUS_MAPPING[$aPGOrder['mData']['order_status']],
                'paid_amount'           => $aPGOrder['mData']['paid_amount']
            ];
            $oOrderModel->updateOrder($mOrder['order_id'], $aOrderUpdate);

            //Prepare Cafe24 return data
            $aCafe24Return = [
                'result_code'    => 0000,
                'result_message' => 'Order paid',
                'key'            => $aRequest['key'],
                'extra_data'     => json_encode($mOrder['extra_data'])
            ];
            // The callback will perform a redirect to the asynchronous payment result endpoint ("/Pay/Recv/openpg/PayReceiveRtn.php") to notify Cafe24
            $sReturnUrl = $mOrder['return_url'] . http_build_query($aCafe24Return);

            // For the sake of the Postman demo request, the function will be terminated here
            echo 'Redirecting to: ' . $sReturnUrl;
            return;

            // When the Cafe24 is notified, they will next call the PG app's payment status endpoint
            return redirect()->secure($sReturnUrl);
        }

        // Update order data if payment was not a success
        $aOrderUpdate = [
            'paid_amount'           => '0.00',
            'pg_order_reference_no' => $aRequest['reference_no'],
            'order_status'          => PGCompanyUtility::PG_STATUS_TO_APP_STATUS_MAPPING[$aPGOrder['mData']['order_status']]
        ];

        $oOrderModel->updateOrder($mOrder['order_id'], $aOrderUpdate);

        // Prepare Cafe24 Return
        $aCafe24Return = [
            'result_code'    => 9999,
            'result_message' => 'Order Not Successful',
            'key'            => $aRequest['key'],
            'extra_data'     => json_encode($mOrder['extra_data'])
        ];

        // The callback will perform a redirect to the Cafe24 return url ("/Pay/Recv/openpg/PayReceiveRtn.php") to notify them
        $sReturnUrl = $mOrder['return_url'] . http_build_query($aCafe24Return);
        echo 'Redirecting to: ' . $sReturnUrl;

        // For the sake of the Postman demo request, the function will be terminated here
        return;

        return redirect()->secure($sReturnUrl);
    }

    /**
     * This endpoint is set in the Cafe24 Developer Center
     * Cafe24 will call this endpoint  -> PG app will provide the status and other data of the order
     * Return: Payment status and data
     * See about return here : https://developers.cafe24.com/app/front/refer/apppg/pgapireference/searchpayment
     */
    public function getPaymentStatus()
    {
        $aRequest = $this->oRequest->all();

        // Validate the parameter
        if (isset($aRequest['key']) === false || $aRequest['key'] === null) {
            abort(400, 'Invalid request');
        }

        // Get the module and order key from the request parameter
        $sRequestKey = $aRequest['key'];
        $aKeyData = explode(':', $sRequestKey);
        $sOrderKey = $aKeyData[1];

        // Get stored order data
        $oOrderModel = new OrderModel('sync-checkout');
        $mOrder = $oOrderModel->getOrder('status-' . $sOrderKey);
        if ($mOrder === null) {
            abort(404, 'Order not found');
        }

        // Create hash string for the return data. See the pattern in the Cafe24 Read Payment Details doc
        $sHashData = $mOrder['paid_amount'] . $mOrder['currency'] . $mOrder['order_id'] . $mOrder['partner_id'] . $mOrder['pg_order_reference_no'];
        $sHashString = base64_encode(hash_hmac('sha256', $sHashData, env('APP_CLIENT_SERVICE'), true));

        // Respond with the payment details
        return Response::json([
            'partner_id' => $mOrder['partner_id'],
            // Check the list of Payment Codes in the Cafe24 docs : https://developers.cafe24.com/app/front/refer/apppg/integrationcode/basiccode
            // Confirm with the Cafe24 representative to know what the value of paymethod should be for your PG Company
            'paymethod' => 'etc',
            'tid' => $mOrder['pg_order_reference_no'],
            'amount' => $mOrder['paid_amount'],
            'order_id' => $mOrder['order_id'],
            'cancel_mode' => 'sync',
            'all_cancel_tf' => 'T',
            'part_cancel_tf' => 'F',
            'escrow_tf' => 'F',
            'currency' => $mOrder['currency'],
            'payed_tf' => ($mOrder['order_status'] === 'paid') ? 'T' : 'F',
            'easy_pay' => 'F', 'hash_data' => $sHashString,
            'extra_data' => $mOrder['extra_data'],
            'result_code' => ($mOrder['order_status'] === 'paid') ? '0000' : '1400',
            'result_message' => ($mOrder['order_status'] === 'paid') ? 'Order paid' : 'Order failed'
        ], 200);
    }
}
