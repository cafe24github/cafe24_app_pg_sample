<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminModel;
use App\Models\TokenModel;
use App\Utilities\Cafe24Utility;
use App\Utilities\PGCompanyUtility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

/**
 * Class AdminController
 * @package App\Http\Controllers\Admin
 */
class AdminController extends Controller
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
     * Show a page that will let merchant input a form for merchant PG keys
     * let merchant enable/disable payment gateway or install/uninstall script
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function displayAdmin()
    {
        // Validate if session exist
        if ($this->oRequest->has('mall_id') === false) {
            session()->flush();
            session()->save();
            abort(403);
        }
        $sMallId = $this->oRequest->get('mall_id');
        if (session()->get($sMallId) === null) {
            session()->flush();
            session()->save();
            abort(403);
        }
        // Get admin settings
        $aAdminSettings = AdminModel::getAdmin($sMallId);

        // Create new admin settings data if none exists for the mall
        if ($aAdminSettings === null) {
            // Get stored token to get mall's shops and store shop info from the Cafe24 API
            $aToken = TokenModel::getAccessToken($sMallId);
            $aShops = Cafe24Utility::getShops($sMallId, $aToken['access_token']);
            $aShops = json_decode($aShops['mData'], true)['shops'];
            $aShopsDataToStore = [];
            foreach ($aShops as $aShop) {
                $aShopsDataToStore[] = [
                    'shop_no'                => $aShop['shop_no'],
                    'shop_name'              => $aShop['shop_name'],
                    'currency_code'          => $aShop['currency_code'],
                    'pg_enabled'             => false,
                    // Additional parameter if checkout type is external checkout.
                    // The payment buttons used for external checkout is added to the mall front by using the Cafe24 Scripttag API which will return a script tag id
                    'script_tag_id'          => null,
                    'external_checkout'      => false,
                    'skins'                  => [
                        'pc'     => $aShop['pc_skin_no'],
                        'mobile' => $aShop['mobile_skin_no']
                    ],
                ];
            }
            $aAdminSettings = [
                'mall_name'     => $sMallId,
                'pg_connected'  => false,
                'pg_public_key' => null,
                'pg_secret_key' => null,
                'shops'         => $aShopsDataToStore
            ];

            // Store admin settings
            AdminModel::storeAdmin($sMallId, $aAdminSettings);
        }

        // Displays the admin settings page
        return view('admin', ['aAdminSettings' => $aAdminSettings]);
    }

    /**
     * Store the merchant's PG company credentials
     * @return \Illuminate\Http\JsonResponse
     */
    public function linkPGAccount()
    {
        $aRequest = $this->oRequest->all();

        // Validate required parameters
        if ($aRequest['mall_id'] === null || $aRequest['public_key'] === null || $aRequest['secret_key'] === null) {
            return Response::json(['message' => 'Invalid request.'], 400);
        }
        $sPublicKey = $aRequest['public_key'];
        $sPrivateKey = $aRequest['secret_key'];

        // Perform a test request to the PG company to validate if credentials requested to the store are valid
        $oPGCompanyUtil = new PGCompanyUtility('admin');
        $oPGCompanyUtil->setPGClient(['public_key' => $sPublicKey, 'secret_key' => $sPrivateKey]);
        $aAccountValidationResult = $oPGCompanyUtil->getOrder('VALIDATION-ORDER');
        if ($aAccountValidationResult['code'] === 401) {
            return Response::json(['message' => $aAccountValidationResult['message']], 401);
        }

        // Store credentials
        $aUpdate = [
            'pg_connected' => true,
            'pg_public_key'   => $sPublicKey,
            'pg_secret_key'  => $sPrivateKey
        ];
        AdminModel::updateAdmin($aRequest['mall_id'], $aUpdate);

        return Response::json(['message' => 'Success Linking'], 200);
    }

    /**
     * Remove the credentials from the admin setting data and disable the PG app in the shops
     * @return \Illuminate\Http\JsonResponse
     */
    public function unlinkPGAccount()
    {
        $aRequest = $this->oRequest->all();

        // Validate request parameters
        if ($aRequest['mall_id'] === null) {
            return Response::json(['message' => 'Invalid request.'], 400);
        }

        // Get admin settings
        $sMallId = $aRequest['mall_id'];
        $aAdminSettings = AdminModel::getAdmin($sMallId);
        if ($aAdminSettings === null) {
            return Response::json(['message' => 'Invalid request.'], 400);
        }

        // Check admin settings if payment is enabled (in the Paymentgateway API)
        if ($aAdminSettings['pg_connected'] === false) {
            return Response::json(['message' => 'Invalid request.'], 400);
        }

        // Get access token from storage. This is needed to use Cafe24 APIs
        $aToken = TokenModel::getAccessToken($sMallId);
        $sToken =  $aToken['access_token'];

        // Loop through all stored shops to disable the PG app on enabled shops
        foreach ($aAdminSettings['shops'] as $iShopIndex => $aShop) {
            if ($aShop['pg_enabled'] === true) {
                Cafe24Utility::disablePGOnShop($sMallId, $aShop['shop_no'], $sToken);

                // If the PG app has external checkout, the JavaScript installed in the shop needs to be removed
                Cafe24Utility::uninstallExternalCheckoutScript($sMallId, $aShop['shop_no'], $aShop['script_tag_id'], $sToken);

                // Update shop data in the admin settings
                $aUpdate = [
                    'shops.' . $iShopIndex . '.pg_enabled'        => false,
                    'shops.' . $iShopIndex . '.script_tag_id'     => null,
                    'shops.' . $iShopIndex . '.external_checkout' => false
                ];
                AdminModel::updateAdmin($sMallId, $aUpdate);
            }
        }

        // Update admin settings data
        $aUpdate = [
            'pg_connected'  => false,
            'pg_public_key' => null,
            'pg_secret_key' => null
        ];
        AdminModel::updateAdmin($sMallId, $aUpdate);

        return Response::json(['message' => 'Success Unlinking'], 200);
    }

    /**
     * Handles enabling/disabling of PG app in shops
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleShops()
    {
        $aRequest = $this->oRequest->all();

        // Validate required parameters
        if ($aRequest['mall_id'] === null || $aRequest['action'] === null || $aRequest['shop_index'] === null) {
            return Response::json(['message' => 'Invalid request.'], 400);
        }

        // Get admin settings
        $sMallId = $aRequest['mall_id'];
        $aAdminSettings = AdminModel::getAdmin($sMallId);
        if ($aAdminSettings === null) {
            return Response::json(['message' => 'Invalid request.'], 400);
        }

        // Check admin settings if payment is enabled (in the Paymentgateway API)
        if ($aAdminSettings['pg_connected'] === false) {
            return Response::json(['message' => 'Invalid request.'], 400);
        }

        $sAction = $aRequest['action'];
        $iShopIndex = $aRequest['shop_index'];
        $aShop = $aAdminSettings['shops'][$iShopIndex];

        // Get access token from storage. This is needed to use Cafe24 APIs
        $aToken = TokenModel::getAccessToken($sMallId);
        $sToken =  $aToken['access_token'];

        // Proceed with enabling the PG app in the shop
        if ($sAction === 'enable') {
            // Verify with the specifications of the PG Company to know what currency is supported
            if (in_array($aShop['currency_code'], PGCompanyUtility::PG_SUPPORTED_CURRENCY) === false) {
                return Response::json(['message' => 'Shop currency is not supported by PG company'], 400);
            }

            // Send request to the Cafe24 PaymentGateway API to enable the PG app in the shop
            $oEnablePGResult = Cafe24Utility::enablePGOnShop($sMallId, $iShopIndex, $aAdminSettings['pg_public_key'], $sToken);
            if (isset($oEnablePGResult['code']) === true && in_array($oEnablePGResult['code'], [404, 422]) === false){
                return Response::json(['message' => 'Failed to enable payment gateway on shop.'], 400);
            }

            // For external checkout: Send request to the Scripttag API to add the payment button in the shop.
            // If the PG Company does not provide their own payment button, you can remove this code block
            $oInstallScriptResult = Cafe24Utility::installExternalCheckoutScript($sMallId, $aShop['shop_no'], $aShop['skins'], $sToken);
            if (isset($oEnablePGResult['code']) === true && in_array($oEnablePGResult['code'], [404, 422]) === false){
                return Response::json(['message' => 'Failed to install script on shop'], 400);
            }

            // Update shop data in the admin settings
            $aUpdate = [
                'shops.' . $iShopIndex . '.pg_enabled'        => true,
                'shops.' . $iShopIndex . '.script_tag_id'     => json_decode($oInstallScriptResult['mData'], true)['scripttag']['script_no'],
                'shops.' . $iShopIndex . '.external_checkout' => true
            ];
            AdminModel::updateAdmin($sMallId, $aUpdate);
        }

        // Proceed with disabling the PG app in the shop
        if ($sAction === 'disable') {
            // Send request to the Cafe24 PaymentGateway API to disable the PG app in the shop
            $oDisablePGResult = Cafe24Utility::disablePGOnShop($sMallId, $aShop['shop_no'], $sToken);
            if (isset($oDisablePGResult['code']) === true && in_array($oDisablePGResult['code'], [404, 422]) === false){
                return Response::json(['message' => 'Failed to disable payment gateway on shop.'], 400);
            }

            // For external checkout: Send request to the Scripttag API to remove the payment button in the shop.
            // If the PG Company does not provide their own payment button, you can remove this code block
            $oInstallScriptResult = Cafe24Utility::uninstallExternalCheckoutScript($sMallId, $aShop['shop_no'], $aShop['script_tag_id'], $sToken);
            if (isset($oInstallScriptResult['code']) === true && in_array($oInstallScriptResult['code'], [404, 422]) === false){
                return Response::json(['message' => 'Failed to uninstall script on shop'], 400);
            }

            // Update shop data in the admin settings
            $aUpdate = [
                'shops.' . $iShopIndex . '.pg_enabled'        => false,
                'shops.' . $iShopIndex . '.script_tag_id'     => null,
                'shops.' . $iShopIndex . '.external_checkout' => false
            ];
            AdminModel::updateAdmin($sMallId, $aUpdate);
        }

        return Response::json(['message' => 'Success', ], 200);
    }
}
