<?php

namespace App\Http\Controllers\ExternalCheckout;

use App\Http\Controllers\Controller;
use App\Libraries\GuzzleLibrary;
use App\Models\AdminModel;
use App\Models\OrderModel;
use App\Models\TokenModel;
use App\Utilities\Cafe24Utility;
use App\Utilities\PGCompanyUtility;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

/**
 * Check checkout flow for sync checkout here: https://developers.cafe24.com/app/front/refer/apppg/paymentprocess/syncapprovepay
 * Check checkout flow for external here : https://developers.cafe24.com/app/front/refer/apppg/paymentprocess/extapprovepay
 * A. Payment Button Initialization
 *  1. EC mall loads page (cart/product page) where JavaScript for the app is installed, calls (getScript)
 *  2. JavaScript -> PG app (getScriptData) to initialize script
 * B. External Checkout
 *  1. When Buyer clicks on the PG Company's payment button, the click function will be triggered with the PG app's JavaScript
 *  2. PG app JavaScript (doCreateHMAC) -> PG app (createOrderHmac) generates hmac *this is a required parameter in requesting Cafe24SDK to reserve an order
 *  3. PG app Javascript (doCreateOrder) -> reserve order in Cafe24 (oCafe24Api.precreateOrder)
 *  4. PG app JavaScript -> PG app (createPayload) prepares checkout data for the PG Company
 *  5. PG app (createPayload) -> request order in PG Company (createCheckout)
 *  6. PG app JavaScript -> initializes the request order to PG Company JavaScript SDK (externalCheckoutButton.initCheckout)
 *  7. PG app (displayOrderPreview) -> PG app (handleExternalCheckoutPayment)
 *  8. PG app (handleExternalCheckoutPayment) redirects to PG company payment page
 *  9. PG Company redirects to -> PG app (handleCallback)
 *  10. PG app (handleCallback) sends updates -> Cafe24 via external NOTY url
 *  11. PG app (handleCallback) redirects to '{ec-mall-domain}/api/shop/pgfail' or '{ec-mall-domain}/api/shop/pgsuccess'
 * @package App\Http\Controllers\ExternalCheckout
 */
class InternalController extends Controller
{
    /**
     * @var Request
     */
    private $oRequest;

    /**
     * @var mixed
     */
    private $sShopNumber;

    /**
     * @var mixed
     */
    private $sShippingType;

    public function __construct(Request $oRequest)
    {
        $this->oRequest = $oRequest;
    }

    /**
     * Endpoint called by the EC mall when the script is installed on the cart or product page
     * EC mall page -> PG app
     * Return : JavaScript that appends the real JavaScript files for the PG app features
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function getScript()
    {
        // Access the JavaScript that appends the functional Javascript files to the page
        $oFileSrc = public_path('js/ScriptCaller/script-caller.js');
        $sFileData = file_get_contents($oFileSrc);

        // Add required headers for browser security
        $aHeaders = [
            'Content-Type'                => 'application/javascript',
            'Cache-Control'               => 'public, max-age=86400',
            'Accept-Ranges'               => 'bytes',
            'Access-Control-Allow-Origin' => '*'
        ];

        return Response::stream(function () use ($sFileData) {
            echo $sFileData;
        }, 200, $aHeaders);
    }

    /**
     * PG Companies need merchant credentials and/or merchant info to initialize their JavaScript button
     * EC mall page (installed script) -> PG app
     * Return : The data needed by the PG Company to initialize their JavaScript (public/secret keys, shop information)
     * @return \Illuminate\Http\JsonResponse
     */
    public function getScriptData()
    {
        $aRequest = $this->oRequest->all();

        // Validate required parameters
        if (isset($aRequest['mall_id']) === false || isset($aRequest['shop_no']) === false || $aRequest['mall_id'] === null || $aRequest['shop_no'] === null) {
            return Response::json([
                'mData'     => 'Invalid request',
                'bResult'   => false
            ], 200);
        }

        $sMallName = $aRequest['mall_id'];
        $iShopNo = (int) $aRequest['shop_no'];

        // Get admin settings
        $mAdmin = AdminModel::getAdmin($sMallName);
        if ($mAdmin === null) {
            return Response::json([
                'mData'     => 'Admin settings does not exist.',
                'bResult'   => false
            ], 200);
        }

        // Check admin settings if payment is enabled (in the Paymentgateway API)
        if ($mAdmin['pg_connected'] === false) {
            return Response::json([
                'mData'     => 'This payment gateway is not enabled in this mall.',
                'bResult'   => false
            ], 200);
        }

        // Get the shop data where the button is initialized
        $aShopData = [];
        foreach ($mAdmin['shops'] as $aShop) {
            if ($aShop['shop_no'] === $iShopNo) {
                $aShopData = $aShop;
            }
        }

        // The returned data is used in the JavaScript function to initialize the PG Company's JavaScript SDK
        return Response::json([
            'mData'     => [
                'public_key'   => $mAdmin['pg_public_key'],
                'shop_name'     => $aShopData['shop_name'],
                'shop_currency' => $aShopData['currency_code'],
            ],
            'bResult'   => true
        ], 200);
    }

    /**
     * This hmac creation is needed in requesting to reserve an order to the Cafe24 JavaScript SDK
     * Read about reserving orders for Cafe24 here : https://developers.cafe24.com/app/front/refer/apppg/pgapireference/extbookingorders
     * You can see the request in the /public/js/DemoAppJS/demo-app-script.js in doCreateOrder function
     * EC mall page (installed script) -> PG app
     * Return : created hmac
     * @return array
     */
    public function createOrderRequestHmac()
    {
        $aRequest = $this->oRequest->all();

        $sMall = $aRequest['mall_id'];
        $sRequestTime = $aRequest['request_time'];
        $sAppKey = $aRequest['client_key'];
        $sMemberId = $aRequest['member_id'];

        // Format the key to be encoded
        // See hash data pattern in the Cafe24 Order Reservation API docs
        $sKey = $sMall . $sRequestTime . $sAppKey . $sMemberId;
        // The APP_SERVICE_KEY is provided in the Cafe24 Developer Center once the PG app is created
        return array('hmac_key' => base64_encode(hash_hmac('sha256', $sKey, env('APP_SERVICE_KEY'), true)));
    }



    /**
     * After the order is reserved in Cafe24, you can now request a checkout to the PG company
     * EC mall page (installed script) -> PG app
     * Return : Data that the PG app needs to request an order
     * @return \Illuminate\Http\JsonResponse
     */
    public function createPayload()
    {
        $aRequest = $this->oRequest->all();

        // Validate required params
        if (isset($aRequest['mall_id']) === false || isset($aRequest['order_id']) === false || isset($aRequest['order']) === false || isset($aRequest['member_id']) === false || $aRequest['mall_id'] === null || $aRequest['order_id'] === null || $aRequest['order'] === null || $aRequest['member_id'] === null) {
            return Response::json([
                'mData'   => 'Invalid request',
                'bResult' => false
            ], 400);
        }

        // Calculate the total amount
        // The total amount is needed to validate the Cafe24 Order Reservation HMAC
        $iTotalOrderAmount = (int) 0;
        $iProductsCount = count($aRequest['order']);
        if ($iProductsCount === 0) {
            return Response::json([
                'mData'     => 'Invalid request : hmac validation failed.',
                'bResult'   => false
            ], 400);
        }
        foreach ($aRequest['order'] as $aProduct) {
            $iPrice = (int) $aProduct['product_price'];
            $iOptionPrice = (int) $aProduct['option_price'];
            $iQuantity = (int) $aProduct['quantity'];
            $this->sShopNumber = $aProduct['shop_no'];

            $iTotalOptionPrice = $iOptionPrice * $iQuantity;
            $iTotalPrice = $iPrice * $iQuantity;
            $iTotalOrderAmount = $iTotalOrderAmount + $iTotalPrice + $iTotalOptionPrice;
        }

        // Validate HMAC given by the Cafe24 Order Reservation
        // For this HMAC pattern, see the response specifications of the Cafe24 Order Reservation docs
        $sRequestHmac = $aRequest['hmac'];
        // The APP_CLIENT_ID is provided in the Cafe24 Developer Center once the PG app is created
        $sHashKey = env('APP_CLIENT_ID') . $aRequest['order_id'];
        $sHashString = (int) $iTotalOrderAmount . $aRequest['order_id'] . $aRequest['response_time'] . $aRequest['return_notification_url'];
        $sCreatedHmac = base64_encode(hash_hmac('sha256', json_encode($sHashString, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $sHashKey, true));
        if ($sRequestHmac !== $sCreatedHmac) {
            return Response::json([
                'mData'     => 'Invalid request : hmac validation failed.',
                'bResult'   => false
            ], 400);
        }

        // Get admin settings
        $mAdmin = AdminModel::getAdmin($aRequest['mall_id']);
        if ($mAdmin === null) {
            return Response::json([
                'mData'     => 'Invalid request : no data found for admin',
                'bResult'   => false
            ], 400);
        }

        // Prepare request data to create the order in the PG Company
        $sReviewUrl = env('APP_URL') . '/api/asynchronous-external-checkout/review/order?' . http_build_query(['mall_id' => $aRequest['mall_id'], 'order_id' => $aRequest['order_id']]);
        $aPGCheckoutRequest = [
            'order_reference_number' => $aRequest['order_id'],
            'amount'                 => $iTotalOrderAmount,
            'currency'               => $aRequest['currency'],
            'extra_field_1'          => $aRequest['return_url_base'],
            'extra_field_2'          => $aRequest['return_notification_url'],
            // If the PG Company requires an order review page, the PG app can provide an endpoint for the page
            'review_url'             => $sReviewUrl
        ];

        // Initialize PG Company http client
        $oPGCompanyUtil = new PGCompanyUtility('synchronous-external-checkout');
        $oPGCompanyUtil->setPGClient(['public_key' => $mAdmin['pg_public_key'], 'secret_key' => $mAdmin['pg_secret_key']]);

        // Send order request
        $aCreateCheckoutResponse = $oPGCompanyUtil->createOrderCheckout($aPGCheckoutRequest);
        if (array_key_exists('mCode', $aCreateCheckoutResponse) === true) {
            return Response::json([
                'mData'     => 'Failed to create checkout',
                'bResult'   => false
            ], 400);
        }
        if ($aCreateCheckoutResponse['mData']['code'] !== 200) {
            return Response::json([
                'mData'     => 'Failed to create checkout : PG company error',
                'bResult'   => false
            ], 400);
        }

        // Store order data to database
        $aOrder = [
            'order_id'               => $aRequest['order_id'],
            'buyer_is_guest'         => $aRequest['bGuest'] === 1,
            'buyer_id'               => $aRequest['member_id'],
            'mall_id'                => $aRequest['mall_id'],
            'shop_no'                => $this->sShopNumber,
            'currency'               => $aRequest['currency'],
            'order_amount'           => $iTotalOrderAmount,
            'order_status'           => PGCompanyUtility::PG_STATUS_TO_APP_STATUS_MAPPING[$aCreateCheckoutResponse['mData']['order_status']],
            'order_data'             => $aRequest['order'],
            'return_url_base'        => $aRequest['return_url_base'],
            'return_noty_url'        => $aRequest['return_notification_url'],
            'pg_order_reference_no'  => $aCreateCheckoutResponse['mData']['reference_no'],
            'pg_refund_reference_no' => null,
            'paid_amount'            => '0',
            'refund_amount'          => null
        ];
        $oOrderModel = new OrderModel('sync-external-checkout');
        $oOrderModel->storeOrder($aOrder['order_id'], $aOrder);

        // The created order is passed down to the PG Company's to initialize their JavaScript SDK
        return Response::json([
            'mData'     => $aCreateCheckoutResponse['mData'],
            'bResult'   => true
        ], 200);
    }

    /**
     * This endpoint will provide a preview of the order request. This endpoint is required depending on the specifications of the PG Company.
     * If the PG company requires the PG app to have a review page/payment page, then the app will send the confirmation to a PG Company's api endpoint that will continue the process.
     * PG Company -> PG app
     * Return : return a view of the order and enable buyer to cancel payment/proceed with the checkout.
     * @return \Illuminate\Http\JsonResponse
     */
    public function displayOrderPreview()
    {
        $aRequest = $this->oRequest->all();

        // Validate required paramaters
        if (isset($aRequest['mall_id']) === false || isset($aRequest['order_id']) === false || $aRequest['mall_id'] === null || $aRequest['order_id'] === null) {
            abort(400,'Invalid request');
        }

        // Get admin settings
        $mAdmin = AdminModel::getAdmin($aRequest['mall_id']);
        if($mAdmin === null) {
            abort(400, 'Admin setting does not exist');
        }

        // Get stored order
        $oOrderModel = new OrderModel('async-external-checkout');
        $mOrder = $oOrderModel->getOrder('review-' . $aRequest['order_id']);
        if($mOrder === null) {
            abort(404, 'Order not found');
        }

        // Get order data from PG Company to validate the request data
        $oPGUtil = new PGCompanyUtility('asynchronous-external-checkout');
        $oPGUtil->setPGClient(['public_key' => $mAdmin['pg_public_key'], 'secret_key' => $mAdmin['pg_secret_key']]);
        $aPGOrder = $oPGUtil->getOrder($mOrder['pg_order_reference_no']);
        if (array_key_exists('mData', $aPGOrder) === false) {
            abort(404, 'Order not found');
        }

        // Prepare data to send an order amount calculation request to the PG Company
        // The order amount is displayed on the preview page for the buyer.
        $sMemberId = ($mOrder['buyer_is_guest'] === true) ? null : $mOrder['buyer_id'];
        $aProducts = $mOrder['order_data'];
        $iProductCount = count($aProducts);
        if ($iProductCount === 0) {
            abort(400, 'Empty cart');
        }

        $aProductListForOrderCalculation = [];
        foreach ($aProducts as $aProduct) {
            $this->sShopNumber = $aProduct['shop_no'];
            $this->sShippingType = $aProduct['shipping_type'];
            $aOrderCalculationProductParams = [
                'product_no'           => (int) $aProduct['product_no'],
                'variant_code'         => $aProduct['variant_code'],
                'quantity'             => $aProduct['quantity'],
                'product_price'        => $aProduct['product_price'],
                'option_price'         => $aProduct['option_price'],
                'product_bundle'       => $aProduct['product_bundle'],
                'prefaid_shipping_fee' => $aProduct['prefaid_shipping_fee']
            ];

            if($aProduct['product_bundle'] === 'T' && (int) $aProduct['product_bundle_no'] !== 0) {
                $aOrderCalculationProductParams['product_bundle_no'] = $aProduct['product_bundle_no'];
            }
            array_push($aProductListForOrderCalculation, $aOrderCalculationProductParams);
        }

        $sZipCode = $aPGOrder['mData']['shipping_address']['postal_code'];
        $sCountryCode = $aPGOrder['mData']['shipping_address']['country_code'];
        $sCity = $aPGOrder['mData']['shipping_address']['city'];
        $sState = $aPGOrder['mData']['shipping_address']['state'];
        // See sample data to send to the API : https://developers.cafe24.com/docs/en/api/admin/#orders-calculation
        $aCalculationRequest = [
            'shop_no' => (int) $this->sShopNumber,
            'request' => [
                'member_id'     => $sMemberId,
                'shipping_type' => $this->sShippingType,
                'country_code'  => strtoupper($sCountryCode),
                'zip_code'      => $sZipCode,
                'address_full'  => $sCity . ',' . $sState,
                'items'         => $aProductListForOrderCalculation
            ]
        ];

        // Get access token for the Cafe24 API request
        $aToken = TokenModel::getAccessToken($mAdmin['mall_name']);
        // Send request to Cafe24
        $aOrderCalculation = Cafe24Utility::requestOrderCalculation($mAdmin['mall_name'], $aCalculationRequest, $aToken['access_token']);
        if (array_key_exists('mData', $aPGOrder) === false) {
            abort(400, 'Error requesting to Cafe24');
        }
        $aOrderCalculation = json_decode($aOrderCalculation['mData'], true)['calculation'];

        // Update order data in database
        $aOrderUpdate    = [
            'pg_payment_reference_no' => $aPGOrder['mData']['payment_reference_no'],
            'order_amount' => $aOrderCalculation['total_amount_due'],
            'shipping_fee' => (int) $aOrderCalculation['shipping_fee'] - (int) $aOrderCalculation['shipping_fee_discount_amount']
        ];
        $oOrderModel->updateOrder($mOrder['order_id'], $aOrderUpdate);

        // Prepare the data for the view
        $aAddressInfo = [
            'name'      => $aPGOrder['mData']['shipping_address']['name'],
            'add_1'     => $aPGOrder['mData']['shipping_address']['add_1'],
            'add_2'     => $aPGOrder['mData']['shipping_address']['add_2'],
            'add_3'     => $aPGOrder['mData']['shipping_address']['add_3'],
            'city'      => $aPGOrder['mData']['shipping_address']['city'],
            'district'  => $aPGOrder['mData']['shipping_address']['district'],
            'state'     => $aPGOrder['mData']['shipping_address']['state'],
            'postal'    => $aPGOrder['mData']['shipping_address']['postal_code'],
            'country'   => $aPGOrder['mData']['shipping_address']['country_code'],
            'phone'     => $aPGOrder['mData']['shipping_address']['phone_number'],
        ];
        $aOrderProducts = $mOrder['order_data'];
        $aShippingFee = [
            'shipping_fee'      => (int) $aOrderCalculation['shipping_fee'] - (int) $aOrderCalculation['shipping_fee_discount_amount'],
            'shipping_discount' => (int) $aOrderCalculation['shipping_fee_discount_amount']
        ];
        $aOrderTotal = [
            'total_amount'        => number_format((int) $aOrderCalculation['total_amount_due'], 0, '', ','),
            'total_shipping_fee'  => number_format((int) $aOrderCalculation['shipping_fee'], 0, '', ','),
            'total_item'          => number_format((int) $aOrderCalculation['order_price_amount'], 0, '', ','),
            'total_discount'      => number_format((int) $aOrderCalculation['total_discount_amount'], 0, '', ','),
            'currency'            => $mOrder['currency'],
        ];

        $aViewData = [
            'mall_id'           => $mAdmin['mall_name'],
            'order_no'          => $mOrder['order_id'],
            'return_button_url' => $mOrder['return_url_base'],
            'address_info'      => $aAddressInfo,
            'preference'        => $aPGOrder['mData']['preference'],
            'order_products'    => $aOrderProducts,
            'shipping_info'     => $aShippingFee,
            'order_info'        => $aOrderTotal
        ];

        // The view is rendered by Laravel Blade
        // return view('review-page', ['aOrderDetails' => $aViewData]);

        // For the sake of the Postman demo request, only the view data will be returned
        return Response::json($aViewData, 200);
    }

    /**
     * This endpoint is called by the payment review page to send the confirmation to the PG Company.
     * If the PG Company requires the app to create their own order confirmation page, this is one of the way to send the confirmation to the PG Company.
     * PG app order review page -> PG app -> PG Company
     * return : URL to redirect to the order result page
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleExternalCheckoutPayment()
    {
        // Validate required params
        $aRequest = $this->oRequest->all();
        if (isset($aRequest['mall_id']) === false || isset($aRequest['order_id']) === false || $aRequest['mall_id'] === null || $aRequest['order_id'] === null) {
            return Response::json([
                'mData'     => [
                    'message'      => 'Invalid request'
                ],
                'bResult'   => false
            ], 400);
        }

        // Get admin settings
        $mAdmin = AdminModel::getAdmin($aRequest['mall_id']);
        if($mAdmin === null) {
            return Response::json([
                'mData'     => [
                    'message'      =>  'Admin setting does not exist',
                    'redirect_url' =>  'https://' . $aRequest['mall_id'] . 'cafe24shop.com'
                ],
                'bResult'   => false
            ], 400);
        }

        // Get stored order data
        $oOrderModel = new OrderModel('sync-external-checkout');
        $mOrder = $oOrderModel->getOrder('pay-' . $aRequest['order_id']);
        if ($mOrder === null) {
            return Response::json([
                'mData'     => [
                    'message'      =>  'Order not found',
                    'redirect_url' =>  'https://' . $aRequest['mall_id'] . 'cafe24shop.com'
                ],
                'bResult'   => false
            ], 404);
        }

        // Initialize PG Company http client to send the payment confirmation request
        $oPGUtil = new PGCompanyUtility('synchronous-external-checkout');
        $oPGUtil->setPGClient(['public_key' => $mAdmin['pg_public_key'], 'secret_key' => $mAdmin['pg_secret_key']]);

        // Prepare request data for the PG Company API
        $aPaymentData = [
            'reference_no'         => $mOrder['pg_order_reference_no'],
            'payment_reference_no' => $mOrder['pg_payment_reference_no'],
            'redirect_url'         => env('APP_URL') . '/api/synchronous-external-checkout/order/callback',
            'amount'               => $mOrder['order_amount']
        ];

        // Send payment confirmation request
        $aPaymentRequest = $oPGUtil->payOrder($mOrder['pg_order_reference_no'], $aPaymentData);
        if (array_key_exists('mData', $aPaymentRequest) === false) {
            return Response::json([
                'mData'     => [
                    'message'      =>  'Payment request failed',
                    'redirect_url' =>  $mOrder['return_url_base']
                ],
                'bResult'   => false
            ], 400);
        }

        // Update order data in database
        $aOrderUpdate = [
            'pg_payment_reference_no' => $aPaymentRequest['mData']['payment_reference_no']
        ];
        $oOrderModel->updateOrder($mOrder['order_id'], $aOrderUpdate);

        // The review page's JavaScript will redirect the buyer to the payment page of the PG Company
        return Response::json([
            'mData'     => [
                'message'      =>  'Success payment',
                'redirect_url' =>  $aPaymentRequest['mData']['redirect_url']
            ],
            'bResult'   => true
        ], 200);
    }

    /**
     * Receive callback request from PG company after the buyer approves the payment
     * PG Company will call this endpoint -> PG App will send updates to Cafe24
     * PG company -> PG app end update via NOTY url -> Cafe24
     * See the Payment Completion Notification for External Checkout here : https://developers.cafe24.com/app/front/refer/apppg/pgapireference/extsuccesspayment
     * Return : redirect url to show payment result
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleExternalCheckoutCallback()
    {
        $aRequest = $this->oRequest->all();

        // Validate required params
        if (isset($aRequest['reference_no']) === false || $aRequest['reference_no'] === null) {
            abort(400, 'Invalid request');
        }

        // Get stored order data
        $oOrderModel = new OrderModel('sync-external-checkout');
        $mOrder = $oOrderModel->getOrder('callback-' . $aRequest['reference_no']);
        if ($mOrder === null) {
            abort(404, 'Order not found');
        }

        // Get admin settings
        $mAdmin = AdminModel::getAdmin($mOrder['mall_id']);
        if ($mAdmin === null) {
            abort(404, 'Admin setting not found');
        }

        // Confirm order data with the PG Company
        $oPGUtil = new PGCompanyUtility('synchronous-external-checkout');
        $oPGUtil->setPGClient(['public_key' => $mAdmin['pg_public_key'], 'secret_key' => $mAdmin['pg_secret_key']]);
        $aPGOrder = $oPGUtil->getOrder($mOrder['pg_order_reference_no']);
        if(array_key_exists('mData', $aPGOrder) === false) {
            abort(404, 'PG order not found');
        }

        // Prepare data for notifying Cafe24 asynchronously
        $sOrderStatus = PGCompanyUtility::PG_STATUS_TO_APP_STATUS_MAPPING[$aPGOrder['mData']['order_status']];
        $sAmount = $aPGOrder['mData']['paid_amount'];
        $bIsPaid = $sOrderStatus === 'paid' ? 'T' : 'F';
        $sPartnerId = $mOrder['mall_id'] . ':' . $mOrder['shop_no'] . ':' . $mAdmin['pg_public_key'];
        $sKey = $sAmount.$mOrder['currency'].$mOrder['order_id'].$sPartnerId.$mOrder['pg_order_reference_no'];
        // The APP_SERVICE_KEY is provided in the Cafe24 Developer Center once the PG app is created
        $sHash = base64_encode(hash_hmac('sha256', $sKey, env('APP_SERVICE_KEY'), true));
        $aTestExtraData = ['pgName' => 'PG-Demo-app'];
        $sResultCode = $sOrderStatus === 'paid' ? '0000' : '9999';
        $sMessage = $sOrderStatus === 'paid' ? 'Payment success' : 'Payment failed';

        try {
            // Send request to the Cafe24 return_noty_url with Guzzle
            $oGuzzle = new GuzzleLibrary(new \GuzzleHttp\Client());
            $aRequestParams = [
                'headers' => ['Content-Type' => 'application/json charset-utf-8'],
                'form_params' => [
                    'request_type'   => 'payment',
                    'partner_id'     => $sPartnerId,
                    // Check the list of Payment Codes in the Cafe24 docs : https://developers.cafe24.com/app/front/refer/apppg/integrationcode/basiccode
                    // Confirm with the Cafe24 representative to know what the value of paymethod should be for your PG Company
                    'paymethod'      => 'etc',
                    'tid'            => $mOrder['pg_order_reference_no'],
                    'amount'         => $sAmount,
                    'order_id'       => $mOrder['order_id'],
                    'all_cancel_tf'  => 'T',
                    'part_cancel_tf' => 'T',
                    'escrow_tf'      => 'F',
                    'currency'       => $mOrder['currency'],
                    'payed_tf'       => $bIsPaid ,
                    'easypay'        => 'F',
                    'hash_data'      => $sHash,
                    'extra_data'     => $aTestExtraData,
                    'result_code'    => $sResultCode,
                    'result_message' => $sMessage
                ]
            ];

            // Send the request to Cafe24
            // $aCafe24NotyResult = $oGuzzle->request('POST', $aRequest['extra_field_2'], $aRequestParams);

            // For the sake of the Postman demo request, the Guzzle result is going to be mocked.
            $aCafe24NotyResult = ['mData' => '{"result" : "OK"}'];
        } catch (GuzzleException $oError) {
            abort(400, 'Something went wrong');
        }

        if (json_decode($aCafe24NotyResult['mData'], true)['result'] !== 'OK') {
            // Update order data in PG app's database if notification failed
            $aOrderUpdate = [
                'order_status'            => 'failed',
                'pg_payment_reference_no' => $aRequest['mData']['payment_reference_no'],
                'paid_amount'             => '0.00',

            ];
            $oOrderModel->updateOrder($mOrder['order_id'], $aOrderUpdate);

            // Prepare the Cafe24 pg_fail endpoint to which the buyer will be redirected to
            $sEndpoint = '/api/shop/pg_fail?order_id=' . $mOrder['order_id'] . '&sync_type=sync';


            // For the sake of the Postman demo request, the process will be terminated here.
            echo 'Redirecting to : ' .  $mOrder['return_url_base'] . $sEndpoint;
            return;

            // The Cafe24 will take care of redirecting the buyer to the mall front
            // return_url_base comes from the precreateOrder SDK of Cafe24 which was requested in the demo-app-script.js (doCreateOrder)
            return  redirect()->secure($mOrder['return_url_base'] . $sEndpoint);
        }

        // Update the order data in the PG app's database once the notification to Cafe24 is successful
        $aOrderUpdate = [
            'order_status'            => $sOrderStatus,
            'pg_payment_reference_no' => $aPGOrder['mData']['payment_reference_no'],
            'paid_amount'             => $sAmount
        ];
        $oOrderModel->updateOrder($mOrder['order_id'], $aOrderUpdate);

        // Prepare the Cafe24 endpoint to which Cafe24 will take care of redirecting the buyer to the proper payment result page
        if ($aPGOrder['mData']['order_status'] === 'S') {
            $sEndpoint = '/api/shop/pgsuccess?order_id=' . $mOrder['order_id'] . '&sync_type=async';
        } else {
            $sEndpoint = '/api/shop/pg_fail?order_id=' . $mOrder['order_id'] . '&sync_type=async';
        }

        // For the sake of the Postman demo request, the process will be terminated here.
        echo 'Redirecting to : ' .  $mOrder['return_url_base'] . $sEndpoint;
        return;

        // The Cafe24 will take care of redirecting the buyer to the mall front
        // return_url_base comes from the precreateOrder SDK of Cafe24 which was requested in the demo-app-script.js (doCreateOrder)
        return  redirect()->secure($mOrder['return_url_base'] . $sEndpoint);
    }
}
