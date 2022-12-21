<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The AdminModel handles the database related functionality of the admin settings data
 * Note: The methods defined in this class are for demo purposes only, please make sure your database operations are foolproof.
 */
class AdminModel
{
    const MOCK_ADMIN_DATA = [
        'mall_name' => 'demomall',
        'pg_connected' => true,
        'pg_public_key' => 'demo-app-public-key',
        'pg_secret_key' => 'demo-app-secret-key',
        'shops' => [
            0 => [
                'shop_no' => 1,
                'shop_name' => 'Tindahan',
                'currency_code' => 'PHP',
                'pg_enabled' => true,
                'script_tag_id' => '1527128695613925',
                'external_checkout' => true,
                'skins' => [
                    'pc' => 47,
                    'mobile' => 48,
                ],
            ],
            1 => [
                'shop_no' => 2,
                'shop_name' => 'TEST 시험 시험Thử nghiệm kiểm thử 試験試験方法01SET-hôm nayᄀᄁᄂᄃᄄᄅᄆᄇᄈᄉᄊᄋᄌᄍᄎᄏᄐᄑ옴옹와완잉의이익임입잉잎의이익인일임입잉잎의이익인일임입잉잎의이익인임입잉잎잎 北京位於華原的西北边缘，背靠燕山，らしい日FF毗园、北海园等。It\'s a great day today❤︎~!',
                'currency_code' => 'JPY',
                'pg_enabled' => false,
                'script_tag_id' => NULL,
                'external_checkout' => false,
                'skins' => [
                    'pc' => 57,
                    'mobile' => 5,
                ],
            ],
            2 => [
                'shop_no' => 3,
                'shop_name' => 'KR shop',
                'currency_code' => 'KRW',
                'pg_enabled' => false,
                'script_tag_id' => NULL,
                'external_checkout' => false,
                'skins' => [
                    'pc' => 6,
                    'mobile' => 7,
                ],
            ],
            3 => [
                'shop_no' => 4,
                'shop_name' => 'JPshop & use $',
                'currency_code' => 'USD',
                'pg_enabled' => false,
                'script_tag_id' => NULL,
                'external_checkout' => false,
                'skins' => [
                    'pc' => 8,
                    'mobile' => 9,
                ],
            ],
            4 => [
                'shop_no' => 6,
                'shop_name' => 'VN Shop',
                'currency_code' => 'VND',
                'pg_enabled' => false,
                'script_tag_id' => NULL,
                'external_checkout' => false,
                'skins' => [
                    'pc' => 13,
                    'mobile' => 14,
                ],
            ],
            5 => [
                'shop_no' => 7,
                'shop_name' => 'PH QA Shop',
                'currency_code' => 'PHP',
                'pg_enabled' => false,
                'script_tag_id' => NULL,
                'external_checkout' => false,
                'skins' => [
                    'pc' => 15,
                    'mobile' => 16,
                ],
            ],
            6 => [
                'shop_no' => 8,
                'shop_name' => 'US QA Shop',
                'currency_code' => 'USD',
                'pg_enabled' => false,
                'script_tag_id' => NULL,
                'external_checkout' => false,
                'skins' => [
                    'pc' => 17,
                    'mobile' => 18,
                ],
            ],
            7 => [
                'shop_no' => 10,
                'shop_name' => 'Newly Added JP Shop',
                'currency_code' => 'JPY',
                'pg_enabled' => false,
                'script_tag_id' => NULL,
                'external_checkout' => false,
                'skins' => [
                    'pc' => 21,
                    'mobile' => 22,
                ],
            ],
            8 => [
                'shop_no' => 12,
                'shop_name' => 'TW QA Shop',
                'currency_code' => 'TWD',
                'pg_enabled' => false,
                'script_tag_id' => NULL,
                'external_checkout' => false,
                'skins' => [
                    'pc' => 25,
                    'mobile' => 26,
                ],
            ],
            9 => [
                'shop_no' => 13,
                'shop_name' => 'QA Shop Settlement Currency Test (Do not Order Here)',
                'currency_code' => 'VND',
                'pg_enabled' => false,
                'script_tag_id' => NULL,
                'external_checkout' => false,
                'skins' => [
                    'pc' => 27,
                    'mobile' => 28,
                ],
            ],
            10 => [
                'shop_no' => 15,
                'shop_name' => 'JP edited',
                'currency_code' => 'JPY',
                'pg_enabled' => false,
                'script_tag_id' => NULL,
                'external_checkout' => false,
                'skins' => [
                    'pc' => 49,
                    'mobile' => 33,
                ],
            ],
            11 => [
                'shop_no' => 17,
                'shop_name' => 'CN Shop',
                'currency_code' => 'USD',
                'pg_enabled' => false,
                'script_tag_id' => NULL,
                'external_checkout' => false,
                'skins' => [
                    'pc' => 41,
                    'mobile' => 37,
                ],
            ],
            12 => [
                'shop_no' => 19,
                'shop_name' => 'KR with USD Shop',
                'currency_code' => 'USD',
                'pg_enabled' => false,
                'script_tag_id' => NULL,
                'external_checkout' => false,
                'skins' => [
                    'pc' => 44,
                    'mobile' => 45,
                ],
            ],
            13 => [
                'shop_no' => 21,
                'shop_name' => 'Angie KR Shop',
                'currency_code' => 'JPY',
                'pg_enabled' => false,
                'script_tag_id' => NULL,
                'external_checkout' => false,
                'skins' => [
                    'pc' => 54,
                    'mobile' => 53,
                ],
            ],
            14 => [
                'shop_no' => 24,
                'shop_name' => 'IN',
                'currency_code' => 'INR',
                'pg_enabled' => false,
                'script_tag_id' => NULL,
                'external_checkout' => false,
                'skins' => [
                    'pc' => 60,
                    'mobile' => 61,
                ],
            ],
        ],
    ];

    public static function getAdmin($sMallId)
    {
        if (empty(session()->all()) === true) {
            return self::MOCK_ADMIN_DATA;
        }
        return session()->get($sMallId . '.admin');
    }

    public static function storeAdmin($sMallId, $aAdmin)
    {
        session()->put($sMallId . '.admin', $aAdmin);
        session()->save();
    }

    public static function updateAdmin($sMallId, $aUpdates)
    {
        foreach ($aUpdates as $sUpdateKey => $sUpdateValue) {
            session()->put($sMallId . '.admin.' . $sUpdateKey, $sUpdateValue);
        }
        session()->save();
    }

}
