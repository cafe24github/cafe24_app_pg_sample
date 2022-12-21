<?php

namespace App\Utilities;

use App\Libraries\GuzzleLibrary;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use App\Models\TokenModel;
use TheSeer\Tokenizer\Token;

/**
 * Utility class for used Cafe24 API Guzzle requests
 */
class Cafe24Utility
{
    /**
     * Request access token : https://developers.cafe24.com/docs/en/api/admin/#get-access-token
     * @param $sAuthCode
     * @param $sMallId
     * @return array
     */
    public static function getAccessToken($sAuthCode, $sMallId)
    {
        // Mock return data for an access token request
        return [
            'mData' => json_encode([
                'access_token'             => 'reCySLMOEamLJLtw4QqZiD',
                'expires_at'               => '2022-07-08T19:50:55.000',
                'refresh_token'            => 'AtDBxeeVlEq7SQs3XV6o6I',
                'refresh_token_expires_at' => '2022-07-22T17:50:55.000',
                'client_id'                => 'iSCr50CoPxDpsmTLgede0A',
                'mall_id'                  => 'demomall',
                'user_id'                  => 'demomall_user',
                'scopes'                   => [
                    0 => 'mall.read_application',
                    1 => 'mall.write_application',
                    2 => 'mall.read_store',
                    3 => 'mall.write_store',
                ],
                'issued_at'                => '2022-07-08T17:50:56.000',
                'shop_no'                  => '1',
            ], true)
        ];

        $sAuthDetail = base64_encode(env('APP_CLIENT_ID') . ':' . env('APP_CLIENT_SECRET'));

        $aHeaders =  [
            'Authorization'        => 'Basic ' . $sAuthDetail,
            'X-Cafe24-Api-Version' => env('CAFE24_API_VERSION'),
            'Content-Type'         => 'application/x-www-form-urlencoded'
        ];

        $aRequestParams = [
            'headers' => $aHeaders,
            'form_params' => [
                'grant_type'   => 'authorization_code',
                'code'         => $sAuthCode,
                'redirect_uri' => env('APP_URL') .  '/auth/token'
            ]
        ];

        $sRequestUrl = sprintf('https://%s.cafe24api.com/api/v2/oauth/token?', $sMallId);

        try {
            $oGuzzle = new GuzzleLibrary(new Client());
            return $oGuzzle->request('POST', $sRequestUrl, $aRequestParams);
        } catch (GuzzleException $oException) {
            return [
                'mCode' => $oException->getCode(),
                'sMessage' => $oException->getMessage()
            ];
        }
    }

    /**
     * Request to refresh access token : https://developers.cafe24.com/docs/en/api/admin/#get-access-token-using-refresh-token
     * @param $sMallId
     * @return mixed
     */
    public static function refreshAccessToken($sMallId)
    {
        $aToken = json_decode(session()->get($sMallId)['token'], true);

        $sAuthDetail = base64_encode(env('APP_CLIENT_ID') . ':' . env('APP_CLIENT_SECRET'));

        $aHeaders =  [
            'Authorization'        => 'Basic ' . $sAuthDetail,
            'X-Cafe24-Api-Version' => env('APP_API_VERSION'),
            'Content-Type'         => 'application/x-www-form-urlencoded'
        ];

        $aRequestParams = [
            'headers' => $aHeaders,
            'form_params' => [
                'grant_type'    => 'refresh_token',
                'refresh_token' => $aToken['refresh_token']
            ]
        ];

        $sRequestUrl = sprintf('https://%s.cafe24api.com/api/v2/oauth/token?', $sMallId);

        try {
            $oGuzzle = new GuzzleLibrary(new Client());
            $aResult = $oGuzzle->request('POST', $sRequestUrl, $aRequestParams);
            if (array_key_exists('mCode', $aResult) === true) {
                session()->forget($sMallId);
                session()->save();
                abort(403);
            }

            TokenModel::storeAccessToken($aResult['mData']);

            return $aResult['mData']->access_token;
        } catch (GuzzleException $oException) {
            session()->forget($sMallId);
            session()->save();
            abort(403);
        }
    }

    /**
     * Get list of shops : https://developers.cafe24.com/docs/en/api/admin/#retrieve-a-list-of-shops
     * @param $sMallId
     * @param $sToken
     * @return array
     */
    public static function getShops($sMallId, $sToken)
    {
        // Mock return data for fetching shops
        return ['mData' => json_encode([
            'shops' => [
                0 => [
                    'shop_no' => 1,
                    'default' => 'T',
                    'shop_name' => '基本ショップ',
                    'business_country_code' => 'JP',
                    'language_code' => 'ja_JP',
                    'language_name' => '日本語',
                    'currency_code' => 'JPY',
                    'currency_name' => 'Japanese Yen (JPY)',
                    'reference_currency_code' => '',
                    'reference_currency_name' => '',
                    'pc_skin_no' => 29,
                    'mobile_skin_no' => 2,
                    'base_domain' => 'ectmtjpq003.cafe24shop.com',
                    'primary_domain' => 'ectmtjpq003.cafe24shop.com',
                    'slave_domain' => [
                    ],
                    'active' => 'T',
                    'timezone' => 'Asia/Tokyo',
                    'timezone_name' => '(UTC+09:00) Osaka, Sapporo, Tokyo',
                    'date_format' => 'YYYY-MM-DD',
                    'time_format' => 'hh:mm:ss',
                    'use_reference_currency' => 'F',
                ],
                1 => [
                    'shop_no' => 2,
                    'default' => 'F',
                    'shop_name' => '대만 몰',
                    'business_country_code' => 'JP',
                    'language_code' => 'zh_TW',
                    'language_name' => '中国語(繁体字)',
                    'currency_code' => 'TWD',
                    'currency_name' => 'Taiwan Dollar (TWD)',
                    'reference_currency_code' => '',
                    'reference_currency_name' => '',
                    'pc_skin_no' => 3,
                    'mobile_skin_no' => 4,
                    'base_domain' => 'shop2.ectmtjpq003.cafe24shop.com',
                    'primary_domain' => 'shop2.ectmtjpq003.cafe24shop.com',
                    'slave_domain' => [
                    ],
                    'active' => 'T',
                    'timezone' => 'Asia/Tokyo',
                    'timezone_name' => '(UTC+09:00) Osaka, Sapporo, Tokyo',
                    'date_format' => 'YYYY-MM-DD',
                    'time_format' => 'hh:mm:ss',
                    'use_reference_currency' => 'F',
                ],
                2 => [
                    'shop_no' => 3,
                    'default' => 'F',
                    'shop_name' => '필리핀 몰',
                    'business_country_code' => 'JP',
                    'language_code' => 'en_PH',
                    'language_name' => '英語(フィリピン)',
                    'currency_code' => 'PHP',
                    'currency_name' => 'Philippine Peso (PHP)',
                    'reference_currency_code' => '',
                    'reference_currency_name' => '',
                    'pc_skin_no' => 5,
                    'mobile_skin_no' => 6,
                    'base_domain' => 'shop3.ectmtjpq003.cafe24shop.com',
                    'primary_domain' => 'shop3.ectmtjpq003.cafe24shop.com',
                    'slave_domain' => [
                    ],
                    'active' => 'T',
                    'timezone' => 'Asia/Tokyo',
                    'timezone_name' => '(UTC+09:00) Osaka, Sapporo, Tokyo',
                    'date_format' => 'YYYY-MM-DD',
                    'time_format' => 'hh:mm:ss',
                    'use_reference_currency' => 'F',
                ],
                3 => [
                    'shop_no' => 4,
                    'default' => 'F',
                    'shop_name' => '한국 멀티몰',
                    'business_country_code' => 'JP',
                    'language_code' => 'ko_KR',
                    'language_name' => '韓国語',
                    'currency_code' => 'KRW',
                    'currency_name' => 'South Korean Won (KRW)',
                    'reference_currency_code' => '',
                    'reference_currency_name' => '',
                    'pc_skin_no' => 9,
                    'mobile_skin_no' => 10,
                    'base_domain' => 'shop4.ectmtjpq003.cafe24shop.com',
                    'primary_domain' => 'shop4.ectmtjpq003.cafe24shop.com',
                    'slave_domain' => [
                    ],
                    'active' => 'T',
                    'timezone' => 'Asia/Tokyo',
                    'timezone_name' => '(UTC+09:00) Osaka, Sapporo, Tokyo',
                    'date_format' => 'YYYY-MM-DD',
                    'time_format' => 'hh:mm:ss',
                    'use_reference_currency' => 'F',
                ],
                4 => [
                    'shop_no' => 5,
                    'default' => 'F',
                    'shop_name' => 'Sypha',
                    'business_country_code' => 'JP',
                    'language_code' => 'ja_JP',
                    'language_name' => '日本語',
                    'currency_code' => 'JPY',
                    'currency_name' => 'Japanese Yen (JPY)',
                    'reference_currency_code' => '',
                    'reference_currency_name' => '',
                    'pc_skin_no' => 13,
                    'mobile_skin_no' => 14,
                    'base_domain' => 'shop5.ectmtjpq003.cafe24shop.com',
                    'primary_domain' => 'shop5.ectmtjpq003.cafe24shop.com',
                    'slave_domain' => [
                    ],
                    'active' => 'T',
                    'timezone' => 'Asia/Tokyo',
                    'timezone_name' => '(UTC+09:00) Osaka, Sapporo, Tokyo',
                    'date_format' => 'YYYY-MM-DD',
                    'time_format' => 'hh:mm:ss',
                    'use_reference_currency' => 'F',
                ],
                5 => [
                    'shop_no' => 6,
                    'default' => 'F',
                    'shop_name' => 'VN Shop',
                    'business_country_code' => 'JP',
                    'language_code' => 'vi_VN',
                    'language_name' => 'ベトナム語',
                    'currency_code' => 'VND',
                    'currency_name' => 'Vietnamese Dong (VND)',
                    'reference_currency_code' => '',
                    'reference_currency_name' => '',
                    'pc_skin_no' => 15,
                    'mobile_skin_no' => 16,
                    'base_domain' => 'shop6.ectmtjpq003.cafe24shop.com',
                    'primary_domain' => 'shop6.ectmtjpq003.cafe24shop.com',
                    'slave_domain' => [
                    ],
                    'active' => 'T',
                    'timezone' => 'Asia/Tokyo',
                    'timezone_name' => '(UTC+09:00) Osaka, Sapporo, Tokyo',
                    'date_format' => 'YYYY-MM-DD',
                    'time_format' => 'hh:mm:ss',
                    'use_reference_currency' => 'F',
                ],
                6 => [
                    'shop_no' => 7,
                    'default' => 'F',
                    'shop_name' => '미국 몰 (US Shop)',
                    'business_country_code' => 'JP',
                    'language_code' => 'en_US',
                    'language_name' => '英語',
                    'currency_code' => 'USD',
                    'currency_name' => 'United States Dollar (USD)',
                    'reference_currency_code' => '',
                    'reference_currency_name' => '',
                    'pc_skin_no' => 22,
                    'mobile_skin_no' => 23,
                    'base_domain' => 'shop7.ectmtjpq003.cafe24shop.com',
                    'primary_domain' => 'shop7.ectmtjpq003.cafe24shop.com',
                    'slave_domain' => [
                    ],
                    'active' => 'T',
                    'timezone' => 'Asia/Tokyo',
                    'timezone_name' => '(UTC+09:00) Osaka, Sapporo, Tokyo',
                    'date_format' => 'YYYY-MM-DD',
                    'time_format' => 'hh:mm:ss',
                    'use_reference_currency' => 'F',
                ],
                7 => [
                    'shop_no' => 8,
                    'default' => 'F',
                    'shop_name' => 'English with JPY',
                    'business_country_code' => 'JP',
                    'language_code' => 'en_US',
                    'language_name' => '英語',
                    'currency_code' => 'JPY',
                    'currency_name' => 'Japanese Yen (JPY)',
                    'reference_currency_code' => '',
                    'reference_currency_name' => '',
                    'pc_skin_no' => 24,
                    'mobile_skin_no' => 25,
                    'base_domain' => 'shop8.ectmtjpq003.cafe24shop.com',
                    'primary_domain' => 'shop8.ectmtjpq003.cafe24shop.com',
                    'slave_domain' => [
                    ],
                    'active' => 'T',
                    'timezone' => 'America/Los_Angeles',
                    'timezone_name' => '(UTC-07:00) Pacific Time (US & Canada)',
                    'date_format' => 'MM-DD-YYYY',
                    'time_format' => 'hh:mm:ss',
                    'use_reference_currency' => 'F',
                ],
            ],
        ], true)];

        $sRequestUrl = sprintf('https://%s.cafe24api.com/api/v2/admin/shops', $sMallId);

        $aHeaders = [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer '. $sToken
        ];

        $aRequestParams = [
            'headers' => $aHeaders,
        ];

        try {
            $oGuzzle = new GuzzleLibrary(new Client());
            $aResult = $oGuzzle->request('GET', $sRequestUrl, $aRequestParams);
            if (array_key_exists('mCode', $aResult) === true && $aResult['mCode'] === 401) {
                $sRefreshedAccessToken = self::refreshAccessToken($sMallId);
                self::getShops($sMallId, $sRefreshedAccessToken);
            }

            return $aResult;
        } catch (GuzzleException $oException) {
            session()->forget($sMallId);
            session()->save();
            abort(403);
        }
    }

    /**
     * Create payment gateway : https://developers.cafe24.com/docs/en/api/admin/#create-a-payment-gateway
     * @param $sMallId
     * @param $iShopNo
     * @param $sPartnerId
     * @param $sToken
     * @return array|string[]
     */
    public static function enablePGOnShop($sMallId, $iShopNo, $sPartnerId, $sToken)
    {
        // Mock API response
        return ['mData' => '{
            "paymentgateway": {
                "shop_no": ' . $iShopNo . ',
                "partner_id": "' . $sMallId . ':' . $iShopNo . ':' . $sPartnerId . '",
                "client_id": "' . env('APP_CLIENT_ID') . '",
                "membership_fee_type": "FREE"
            }
        }'];

        $sRequestUrl = 'https://'. $sMallId . '.cafe24api.com/api/v2/admin/paymentgateway';

        $aHeaders = [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer '. $sToken
        ];

        $aParams = [
            'shop_no' => $iShopNo,
            'request' => [
                'partner_id' => $iShopNo . ':' . $sPartnerId,
                'membership_fee_type' => 'FREE'
            ]
        ];

        $aRequestParams = [
            'headers'     => $aHeaders,
            'form_params' => $aParams
        ];

        try {
            $oGuzzle = new GuzzleLibrary(new Client());
            $aResult = $oGuzzle->request('DELETE', $sRequestUrl, $aRequestParams);
            if (array_key_exists('mCode', $aResult) === true && $aResult['mCode'] === 401) {
                $sRefreshedAccessToken = self::refreshAccessToken($sMallId);
                self::enablePGOnShop($sMallId, $iShopNo, $sPartnerId, $sRefreshedAccessToken);
            }

            return $aResult;
        } catch (GuzzleException $oException) {
            session()->forget($sMallId);
            session()->save();
            abort(403);
        }
    }

    /**
     * Delete payment gateway : https://developers.cafe24.com/docs/en/api/admin/#delete-a-payment-gateway
     * @param $sMallId
     * @param $iShopNo
     * @param $sToken
     * @return array|string[]
     */
    public static function disablePGOnShop($sMallId, $iShopNo, $sToken)
    {
        // Mock API response
        return ['mData' => '{"paymentgateway": {"shop_no": ' . $iShopNo . ',"client_id": "' .  env('APP_CLIENT_ID') . '"}}'];

        $sRequestUrl = 'https://'. $sMallId . '.cafe24api.com/api/v2/admin/paymentgateway/' . env('APP_CLIENT_ID') . '?shop_no=' . $iShopNo;

        $aHeaders = [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer '. $sToken
        ];

        $aRequestParams = [
            'headers' => $aHeaders,
        ];

        try {
            $oGuzzle = new GuzzleLibrary(new Client());
            $aResult = $oGuzzle->request('DELETE', $sRequestUrl, $aRequestParams);
            if (array_key_exists('mCode', $aResult) === true && $aResult['mCode'] === 401) {
                $sRefreshedAccessToken = self::refreshAccessToken($sMallId);
                self::disablePGOnShop($sMallId, $iShopNo, $sRefreshedAccessToken);
            }

            return $aResult;
        } catch (GuzzleException $oException) {
            session()->forget($sMallId);
            session()->save();
            abort(403);
        }
    }

    /**
     * Install script on pages : https://developers.cafe24.com/docs/en/api/admin/#create-a-script-tag
     * @param $sMallId
     * @param $iShopNo
     * @param $aSkins
     * @param $sToken
     * @return array|string[]
     */
    public static function installExternalCheckoutScript($sMallId, $iShopNo, $aSkins, $sToken)
    {
        $sScriptFileData = file_get_contents(public_path('/js/ScriptCaller/script-caller.js'));
        $sSRI = 'sha384-' . base64_encode(hash('sha384', $sScriptFileData, true));

        // Mock API success result
        return ['mData' => '{
            "scripttag": {
                "shop_no": '. $iShopNo .',
                "script_no": "1527128695613925",
                "client_id": "' . env('APP_CLIENT_ID') . '",
                "src": "' . route('script-caller') . '",
                "display_location": [
                    "PRODUCT_DETAIL",
                    "ORDER_BASKET"
                ],
                "skin_no": [
                    ' . $aSkins['pc'] . ',
                    ' . $aSkins['mobile'] . '
                ],
                "integrity": "' . $sSRI . '",
                "created_date": "2017-03-15T13:27:53+09:00",
                "updated_date": "2017-03-15T13:27:53+09:00"
            }
        }'];

        $aParams = [
            'shop_no' => $iShopNo,
            'request' => [
                'display_location' => ['PRODUCT_DETAIL', 'ORDER_BASKET'],
                'src'              => route('script-caller'),
                'skin_no'          => [$aSkins['pc'], $aSkins['mobile']],
                'integrity'        => $sSRI
            ]
        ];

        $sRequestUrl = 'https://'. $sMallId . '.cafe24api.com/api/v2/admin/scripttags';

        $aHeaders = [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer '. $sToken
        ];

        $aRequestParams = [
            'headers'     => $aHeaders,
            'form_params' => $aParams
        ];

        try {
            $oGuzzle = new GuzzleLibrary(new Client());
            $aResult = $oGuzzle->request('DELETE', $sRequestUrl, $aRequestParams);
            if (array_key_exists('mCode', $aResult) === true && $aResult['mCode'] === 401) {
                $sRefreshedAccessToken = self::refreshAccessToken($sMallId);
                self::installExternalCheckoutScript($sMallId, $iShopNo, $aSkins, $sRefreshedAccessToken);
            }

            return $aResult;
        } catch (GuzzleException $oException) {
            session()->forget($sMallId);
            session()->save();
            abort(403);
        }
    }

    /**
     * Delete script tag : https://developers.cafe24.com/docs/en/api/admin/#delete-a-script-tag
     * @param $sMallId
     * @param $iShopNo
     * @param $sScriptTagId
     * @param $sToken
     * @return array|string[]
     */
    public static function uninstallExternalCheckoutScript($sMallId, $iShopNo, $sScriptTagId, $sToken)
    {
        // Mock API response
        return ['mData' => '{"scripttag": {"script_no": "' . $sScriptTagId . '"}}'];

        $sRequestUrl = 'https://'. $sMallId . '.cafe24api.com/api/v2/admin/scripttags/' . $sScriptTagId . '?shop_no=' . $iShopNo;
        $aHeaders = [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer '. $sToken
        ];
        $aRequestParams = [
            'headers'     => $aHeaders,
        ];
        try {
            $oGuzzle = new GuzzleLibrary(new Client());
            $aResult = $oGuzzle->request('DELETE', $sRequestUrl, $aRequestParams);
            if (array_key_exists('mCode', $aResult) === true && $aResult['mCode'] === 401) {
                $sRefreshedAccessToken = self::refreshAccessToken($sMallId);
                self::uninstallExternalCheckoutScript($sMallId, $iShopNo, $sScriptTagId, $sRefreshedAccessToken);
            }
            return $aResult;
        } catch (GuzzleException $oException) {
            session()->forget($sMallId);
            session()->save();
            abort(403);
        }
    }

    /**
     * request calculation for order amount : https://developers.cafe24.com/docs/en/api/admin/#calculate-total-due
     * @param $sMallId
     * @param $sToken
     * @return array
     */
    public static function requestOrderCalculation($sMallId, $aCalculationRequest, $sToken)
    {
        // Mock success result
        return ['mData' => '{
                "calculation": {
                "shop_no": 1,
                "mobile": "F",
                "member_id": "sampleid",
                "payment_method": "cash",
                "shipping_type": "A",
                "country_code": "PH",
                "carrier_id": 15,
                "zipcode": "1550",
                "address_full": "Mandaluyong,NCR",
                "address_state": "STATE",
                "items": [
                    {
                        "product_no": 22,
                        "variant_code": "P000000W000C",
                        "quantity": 1,
                        "product_price": "1000.00",
                        "option_price": "100.00",
                        "product_bundle": "F",
                        "product_bundle_no": null,
                        "prepaid_shipping_fee": "P",
                        "additional_discount_price": "0.00",
                        "additional_discount_detail": {
                            "customer_discount_amount": null,
                            "new_product_discount_amount": null,
                            "individual_bundle_product_discount_amount": "0.00",
                            "repurchase_discount_amount": null,
                            "bulk_purchase_discount_amount": null,
                            "period_discount_amount": "0,00"
                        }
                    },
                    {
                        "product_no": 22,
                        "variant_code": "P000000W000A",
                        "quantity": 1,
                        "product_price": "1000.00",
                        "option_price": "200.00",
                        "product_bundle": "F",
                        "product_bundle_no": null,
                        "prepaid_shipping_fee": "P",
                        "additional_discount_price": "0.00",
                        "additional_discount_detail": {
                            "customer_discount_amount": null,
                            "new_product_discount_amount": null,
                            "individual_bundle_product_discount_amount": "0,00",
                            "repurchase_discount_amount": null,
                            "bulk_purchase_discount_amount": null,
                            "period_discount_amount": "0.00"
                        }
                    }
                ],
                "membership_discount_amount": "800.00",
                "shipping_fee_discount_amount": "200.00",
                "product_discount_amount": "4080.00",
                "order_price_amount": "13600.00",
                "total_discount_amount": "7580.00",
                "shipping_fee": "500.00",
                "total_amount_due": "8720.00",
                "shipping_fee_information": {
                    "default_shipping_fee": "100.00",
                    "supplier_shipping_fee": null,
                    "additonal_abroad_shipping_fee": null,
                    "additional_handling_fee": null
                }
            }
        }'];

        $sRequestUrl = 'https://'. $sMallId . '.cafe24api.com/api/v2/admin/orders/calculation';

        $aHeaders = [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer '. $sToken
        ];

        $aRequestParams = [
            'headers'     => $aHeaders,
            'form_params' => $aCalculationRequest
        ];

        try {
            $oGuzzle = new GuzzleLibrary(new Client());
            $aResult = $oGuzzle->request('POST', $sRequestUrl, $aRequestParams);
            if (array_key_exists('mCode', $aResult) === true && $aResult['mCode'] === 401) {
                $sRefreshedAccessToken = self::refreshAccessToken($sMallId);
                self::requestOrderCalculation($sMallId, $aCalculationRequest, $sRefreshedAccessToken);
            }

            return $aResult;
        } catch (GuzzleException $oException) {
            session()->forget($sMallId);
            session()->save();
            abort(403);
        }
    }
}
