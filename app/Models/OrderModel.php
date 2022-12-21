<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The OrderModel handles the database related functionality of the order data
 * Note: The methods defined in this class are for demo purposes only, please make sure your database operations are foolproof.
 */
class OrderModel
{
    private $sModule;

    public function __construct($sModule) {
        $this->sModule = $sModule;
    }

    const MOCK_ORDER_DATA = [
        'async-checkout'          => [
            'callback-20220519-0000029' => [
                'order_id' => '20220519-0000029',
                'mall_name' => 'demomall',
                'partner_id' => 'demomall:0:demo-app-public-key"',
                'buyer_id' => 'mockbuyer',
                'shop_no' => '1',
                'order_status' => 'requested',
                'checkout_request_amount' => '3065.00',
                'return_url' => 'https://demomall.cafe24shop.com/Pay/Recv/openpg/PayReceiveRtnPage.php',
                'return_noty_url' => 'https://demomall.cafe24shop.com/Pay/Recv/openpg/PayReceiveNoty.php',
                'pg_order_reference_no' => NULL,
                'pg_refund_reference_no' => NULL,
                'paid_amount' => NULL,
                'refund_amount' => NULL,
                'extra_data' => [
                    'pgName'     => 'pg-demo-app',
                    'orderId'    => '20220519-0000029',
                    'LogKey'     => 'PGPy051917134394802320q941c03955e4qt9415',
                    'sRdrLogKey' => 'PGPy051917134394802350q052c25850tq12501e',
                ]
            ],
            'webhook-20220519-0000029'  => [
                'order_id' => '20220519-0000029',
                'mall_name' => 'demomall',
                'partner_id' => 'demomall:0:demo-app-public-key',
                'buyer_id' => 'mockbuyer',
                'shop_no' => '1',
                'order_status' => 'pending',
                'checkout_request_amount' => '3065.00',
                'return_url' => 'https://demomall.cafe24shop.com/Pay/Recv/openpg/PayReceiveRtnPage.php',
                'return_noty_url' => 'https://demomall.cafe24shop.com/Pay/Recv/openpg/PayReceiveNoty.php',
                'pg_order_reference_no' => 'pg-order-001',
                'pg_refund_reference_no' => NULL,
                'paid_amount' => '0.00',
                'refund_amount' => NULL,
                'extra_data' => [
                    'pgName'     => 'pg-demo-app',
                    'orderId'    => '20220519-0000029',
                    'LogKey'     => 'PGPy051917134394802320q941c03955e4qt9415',
                    'sRdrLogKey' => 'PGPy051917134394802350q052c25850tq12501e',
                ],
            ]
        ],
        'async-external-checkout' => [
            'review-20220513-0000079'   => [
                'order_id' => '20220513-0000079',
                'buyer_is_guest' => false,
                'buyer_id' => 'mockbuyer',
                'mall_id' => 'demomall',
                'shop_no' => 1,
                'currency' => 'PHP',
                'order_amount' => '1900.00',
                'order_status' => 'pending',
                'order_data' => [
                    0 => [
                        'shop_no' => 1,
                        'category_no' => 24,
                        'quantity' => 1,
                        'additional_option_values' => NULL,
                        'variant_code' => 'P000000W000C',
                        'product_bundle' => 'F',
                        'prefaid_shipping_fee' => 'P',
                        'attached_file_option' => NULL,
                        'created_date' => '2022-05-13 11:20:32',
                        'product_price' => '1000.00',
                        'option_price' => '100.00',
                        'product_bundle_price' => NULL,
                        'product_no' => 22,
                        'option_id' => '000C',
                        'product_bundle_no' => 0,
                        'shipping_type' => 'B',
                        'subscription' => 'F',
                        'subscription_cycle' => NULL,
                        'subscription_shipments_cycle_count' => 0,
                        'basket_product_no' => 3111,
                        'product_name' => 'Basic Product (Custom Variant)',
                        'checked_products' => 'T',
                        'option_text' => 'サイズ=S',
                        'product_bundle_list' => NULL,
                    ],
                    1 => [
                        'shop_no' => 1,
                        'category_no' => 24,
                        'quantity' => 1,
                        'additional_option_values' => NULL,
                        'variant_code' => 'P000000W000A',
                        'product_bundle' => 'F',
                        'prefaid_shipping_fee' => 'P',
                        'attached_file_option' => NULL,
                        'created_date' => '2022-05-13 11:20:32',
                        'product_price' => '1000.00',
                        'option_price' => '-200.00',
                        'product_bundle_price' => NULL,
                        'product_no' => 22,
                        'option_id' => '000A',
                        'product_bundle_no' => 0,
                        'shipping_type' => 'B',
                        'subscription' => 'F',
                        'subscription_cycle' => NULL,
                        'subscription_shipments_cycle_count' => 0,
                        'basket_product_no' => 3112,
                        'product_name' => 'Basic Product (Custom Variant)',
                        'checked_products' => 'T',
                        'option_text' => 'カラー=Black',
                        'product_bundle_list' => NULL,
                    ],
                ],
                'return_url_base' => 'http://demomall.cafe24shop.com',
                'return_noty_url' => 'http://demomall.cafe24shop.com/Pay/Recv/openpg/PayReceiveCheckoutNoty.php',
                'pg_order_reference_no' => 'pg-order-001',
                'pg_refund_reference_no' => NULL,
                'paid_amount' =>  NULL,
                'refund_amount' => NULL,
            ],
            'pay-20220513-0000079'      => [
                'order_id' => '20220513-0000079',
                'buyer_is_guest' => false,
                'buyer_id' => 'mockbuyer',
                'mall_id' => 'demomall',
                'shop_no' => 1,
                'currency' => 'PHP',
                'order_amount' => '8720.00',
                'shipping_fee' => '300.00',
                'order_status' => 'authorized',
                'order_data' => [
                    0 => [
                        'shop_no' => 1,
                        'category_no' => 24,
                        'quantity' => 1,
                        'additional_option_values' => NULL,
                        'variant_code' => 'P000000W000C',
                        'product_bundle' => 'F',
                        'prefaid_shipping_fee' => 'P',
                        'attached_file_option' => NULL,
                        'created_date' => '2022-05-13 11:20:32',
                        'product_price' => '1000.00',
                        'option_price' => '100.00',
                        'product_bundle_price' => NULL,
                        'product_no' => 22,
                        'option_id' => '000C',
                        'product_bundle_no' => 0,
                        'shipping_type' => 'B',
                        'subscription' => 'F',
                        'subscription_cycle' => NULL,
                        'subscription_shipments_cycle_count' => 0,
                        'basket_product_no' => 3111,
                        'product_name' => 'Basic Product (Custom Variant)',
                        'checked_products' => 'T',
                        'option_text' => 'サイズ=S',
                        'product_bundle_list' => NULL,
                    ],
                    1 => [
                        'shop_no' => 1,
                        'category_no' => 24,
                        'quantity' => 1,
                        'additional_option_values' => NULL,
                        'variant_code' => 'P000000W000A',
                        'product_bundle' => 'F',
                        'prefaid_shipping_fee' => 'P',
                        'attached_file_option' => NULL,
                        'created_date' => '2022-05-13 11:20:32',
                        'product_price' => '1000.00',
                        'option_price' => '-200.00',
                        'product_bundle_price' => NULL,
                        'product_no' => 22,
                        'option_id' => '000A',
                        'product_bundle_no' => 0,
                        'shipping_type' => 'B',
                        'subscription' => 'F',
                        'subscription_cycle' => NULL,
                        'subscription_shipments_cycle_count' => 0,
                        'basket_product_no' => 3112,
                        'product_name' => 'Basic Product (Custom Variant)',
                        'checked_products' => 'T',
                        'option_text' => 'カラー=Black',
                        'product_bundle_list' => NULL,
                    ],
                ],
                'return_url_base' => 'http://demomall.cafe24shop.com',
                'return_noty_url' => 'http://demomall.cafe24shop.com/Pay/Recv/openpg/PayReceiveCheckoutNoty.php',
                'pg_order_reference_no' => 'pg-order-001',
                'pg_payment_reference_no' => 'pg-payment-001',
                'pg_refund_reference_no' => NULL,
                'paid_amount' => NULL,
                'refund_amount' => NULL,
            ],
            'callback-20220513-0000079' => [
                'order_id' => '20220513-0000079',
                'buyer_is_guest' => false,
                'buyer_id' => 'mockbuyer',
                'mall_id' => 'demomall',
                'shop_no' => 1,
                'currency' => 'PHP',
                'order_amount' => '1900.00',
                'shipping_fee' => '300.00',
                'order_status' => 'authorized',
                'order_data' => [
                    0 => [
                        'shop_no' => 1,
                        'category_no' => 24,
                        'quantity' => 1,
                        'additional_option_values' => NULL,
                        'variant_code' => 'P000000W000C',
                        'product_bundle' => 'F',
                        'prefaid_shipping_fee' => 'P',
                        'attached_file_option' => NULL,
                        'created_date' => '2022-05-13 11:20:32',
                        'product_price' => 1000,
                        'option_price' => 100,
                        'product_bundle_price' => NULL,
                        'product_no' => 22,
                        'option_id' => '000C',
                        'product_bundle_no' => 0,
                        'shipping_type' => 'B',
                        'subscription' => 'F',
                        'subscription_cycle' => NULL,
                        'subscription_shipments_cycle_count' => 0,
                        'basket_product_no' => 3111,
                        'product_name' => 'Basic Product (Custom Variant)',
                        'checked_products' => 'T',
                        'option_text' => 'サイズ=S',
                        'product_bundle_list' => NULL,
                    ],
                    1 => [
                        'shop_no' => 1,
                        'category_no' => 24,
                        'quantity' => 1,
                        'additional_option_values' => NULL,
                        'variant_code' => 'P000000W000A',
                        'product_bundle' => 'F',
                        'prefaid_shipping_fee' => 'P',
                        'attached_file_option' => NULL,
                        'created_date' => '2022-05-13 11:20:32',
                        'product_price' => 1000,
                        'option_price' => -200,
                        'product_bundle_price' => NULL,
                        'product_no' => 22,
                        'option_id' => '000A',
                        'product_bundle_no' => 0,
                        'shipping_type' => 'B',
                        'subscription' => 'F',
                        'subscription_cycle' => NULL,
                        'subscription_shipments_cycle_count' => 0,
                        'basket_product_no' => 3112,
                        'product_name' => 'Basic Product (Custom Variant)',
                        'checked_products' => 'T',
                        'option_text' => 'カラー=Black',
                        'product_bundle_list' => NULL,
                    ],
                ],
                'return_url_base' => 'http://demomall.cafe24shop.com',
                'return_noty_url' => 'http://demomall.cafe24shop.com/Pay/Recv/openpg/PayReceiveCheckoutNoty.php',
                'pg_order_reference_no' => 'pg-order-001',
                'pg_payment_reference_no' => 'pg-payment-001',
                'pg_refund_reference_no' => NULL,
                'paid_amount' => NULL,
                'refund_amount' => NULL,
            ],
        ],
        'sync-checkout'           => [
            'checkout-20220519-0000029' => [
                'order_id' => '20220519-0000029',
                'mall_name' => 'demomall',
                'partner_id' => 'demomall:0:demo-app-public-key',
                'buyer_id' => 'mockbuyer',
                'shop_no' => '1',
                'order_status' => 'pending',
                'currency' => 'PHP',
                'checkout_request_amount' => '3065.00',
                'return_url' => 'https://demomall.cafe24shop.com/Pay/Recv/openpg/PayReceiveRtnPage.php',
                'pg_order_reference_no' => NULL,
                'pg_refund_reference_no' => NULL,
                'paid_amount' => '0.00',
                'refund_amount' => NULL,
                'extra_data' => [
                    'pgName'     => 'pg-demo-app',
                    'orderId'    => '20220519-0000029',
                    'LogKey'     => 'PGPy051917134394802320q941c03955e4qt9415',
                    'sRdrLogKey' => 'PGPy051917134394802350q052c25850tq12501e',
                ]
            ],
            'status-20220519-0000029'   => [
                'order_id' => '20220519-0000029',
                'mall_name' => 'demomall',
                'partner_id' => 'demomall:0:demo-app-public-key',
                'buyer_id' => 'mockbuyer',
                'shop_no' => '1',
                'order_status' => 'paid',
                'currency' => 'PHP',
                'checkout_request_amount' => '3065.00',
                'return_url' => 'https://demomall.cafe24shop.com/Pay/Recv/openpg/PayReceiveRtnPage.php',
                'pg_order_reference_no' => 'pg-order-001',
                'pg_refund_reference_no' => NULL,
                'paid_amount' => '3065.00',
                'refund_amount' => NULL,
                'extra_data' => [
                    'pgName'     => 'pg-demo-app',
                    'orderId'    => '20220519-0000029',
                    'LogKey'     => 'PGPy051917134394802320q941c03955e4qt9415',
                    'sRdrLogKey' => 'PGPy051917134394802350q052c25850tq12501e',
                ]
            ]
        ],
        'sync-external-checkout'  => [
            'review-20220513-0000079'   => [
                'order_id' => '20220513-0000079',
                'buyer_is_guest' => false,
                'buyer_id' => 'mockbuyer',
                'mall_id' => 'demomall',
                'shop_no' => 1,
                'currency' => 'PHP',
                'order_amount' => '1900.00',
                'order_status' => 'pending',
                'order_data' => [
                    0 => [
                        'shop_no' => 1,
                        'category_no' => 24,
                        'quantity' => 1,
                        'additional_option_values' => NULL,
                        'variant_code' => 'P000000W000C',
                        'product_bundle' => 'F',
                        'prefaid_shipping_fee' => 'P',
                        'attached_file_option' => NULL,
                        'created_date' => '2022-05-13 11:20:32',
                        'product_price' => '1000.00',
                        'option_price' => '100.00',
                        'product_bundle_price' => NULL,
                        'product_no' => 22,
                        'option_id' => '000C',
                        'product_bundle_no' => 0,
                        'shipping_type' => 'B',
                        'subscription' => 'F',
                        'subscription_cycle' => NULL,
                        'subscription_shipments_cycle_count' => 0,
                        'basket_product_no' => 3111,
                        'product_name' => 'Basic Product (Custom Variant)',
                        'checked_products' => 'T',
                        'option_text' => 'サイズ=S',
                        'product_bundle_list' => NULL,
                    ],
                    1 => [
                        'shop_no' => 1,
                        'category_no' => 24,
                        'quantity' => 1,
                        'additional_option_values' => NULL,
                        'variant_code' => 'P000000W000A',
                        'product_bundle' => 'F',
                        'prefaid_shipping_fee' => 'P',
                        'attached_file_option' => NULL,
                        'created_date' => '2022-05-13 11:20:32',
                        'product_price' => '1000.00',
                        'option_price' => '-200.00',
                        'product_bundle_price' => NULL,
                        'product_no' => 22,
                        'option_id' => '000A',
                        'product_bundle_no' => 0,
                        'shipping_type' => 'B',
                        'subscription' => 'F',
                        'subscription_cycle' => NULL,
                        'subscription_shipments_cycle_count' => 0,
                        'basket_product_no' => 3112,
                        'product_name' => 'Basic Product (Custom Variant)',
                        'checked_products' => 'T',
                        'option_text' => 'カラー=Black',
                        'product_bundle_list' => NULL,
                    ],
                ],
                'return_url_base' => 'http://demomall.cafe24shop.com',
                'return_noty_url' => 'http://demomall.cafe24shop.com/Pay/Recv/openpg/PayReceiveCheckoutNoty.php',
                'pg_order_reference_no' => 'pg-order-001',
                'pg_refund_reference_no' => NULL,
                'paid_amount' =>  NULL,
                'refund_amount' => NULL,
            ],
            'pay-20220513-0000079'      => [
                'order_id' => '20220513-0000079',
                'buyer_is_guest' => false,
                'buyer_id' => 'mockbuyer',
                'mall_id' => 'demomall',
                'shop_no' => 1,
                'currency' => 'PHP',
                'order_amount' => '8720.00',
                'shipping_fee' => '300.00',
                'order_status' => 'authorized',
                'order_data' => [
                    0 => [
                        'shop_no' => 1,
                        'category_no' => 24,
                        'quantity' => 1,
                        'additional_option_values' => NULL,
                        'variant_code' => 'P000000W000C',
                        'product_bundle' => 'F',
                        'prefaid_shipping_fee' => 'P',
                        'attached_file_option' => NULL,
                        'created_date' => '2022-05-13 11:20:32',
                        'product_price' => '1000.00',
                        'option_price' => '100.00',
                        'product_bundle_price' => NULL,
                        'product_no' => 22,
                        'option_id' => '000C',
                        'product_bundle_no' => 0,
                        'shipping_type' => 'B',
                        'subscription' => 'F',
                        'subscription_cycle' => NULL,
                        'subscription_shipments_cycle_count' => 0,
                        'basket_product_no' => 3111,
                        'product_name' => 'Basic Product (Custom Variant)',
                        'checked_products' => 'T',
                        'option_text' => 'サイズ=S',
                        'product_bundle_list' => NULL,
                    ],
                    1 => [
                        'shop_no' => 1,
                        'category_no' => 24,
                        'quantity' => 1,
                        'additional_option_values' => NULL,
                        'variant_code' => 'P000000W000A',
                        'product_bundle' => 'F',
                        'prefaid_shipping_fee' => 'P',
                        'attached_file_option' => NULL,
                        'created_date' => '2022-05-13 11:20:32',
                        'product_price' => '1000.00',
                        'option_price' => '-200.00',
                        'product_bundle_price' => NULL,
                        'product_no' => 22,
                        'option_id' => '000A',
                        'product_bundle_no' => 0,
                        'shipping_type' => 'B',
                        'subscription' => 'F',
                        'subscription_cycle' => NULL,
                        'subscription_shipments_cycle_count' => 0,
                        'basket_product_no' => 3112,
                        'product_name' => 'Basic Product (Custom Variant)',
                        'checked_products' => 'T',
                        'option_text' => 'カラー=Black',
                        'product_bundle_list' => NULL,
                    ],
                ],
                'return_url_base' => 'http://demomall.cafe24shop.com',
                'return_noty_url' => 'http://demomall.cafe24shop.com/Pay/Recv/openpg/PayReceiveCheckoutNoty.php',
                'pg_order_reference_no' => 'pg-order-001',
                'pg_payment_reference_no' => 'pg-payment-001',
                'pg_refund_reference_no' => NULL,
                'paid_amount' => NULL,
                'refund_amount' => NULL,
            ],
            'callback-20220513-0000079' => [
                'order_id' => '20220513-0000079',
                'buyer_is_guest' => false,
                'buyer_id' => 'mockbuyer',
                'mall_id' => 'demomall',
                'shop_no' => 1,
                'currency' => 'PHP',
                'order_amount' => '1900.00',
                'shipping_fee' => '300.00',
                'order_status' => 'authorized',
                'order_data' => [
                    0 => [
                        'shop_no' => 1,
                        'category_no' => 24,
                        'quantity' => 1,
                        'additional_option_values' => NULL,
                        'variant_code' => 'P000000W000C',
                        'product_bundle' => 'F',
                        'prefaid_shipping_fee' => 'P',
                        'attached_file_option' => NULL,
                        'created_date' => '2022-05-13 11:20:32',
                        'product_price' => 1000,
                        'option_price' => 100,
                        'product_bundle_price' => NULL,
                        'product_no' => 22,
                        'option_id' => '000C',
                        'product_bundle_no' => 0,
                        'shipping_type' => 'B',
                        'subscription' => 'F',
                        'subscription_cycle' => NULL,
                        'subscription_shipments_cycle_count' => 0,
                        'basket_product_no' => 3111,
                        'product_name' => 'Basic Product (Custom Variant)',
                        'checked_products' => 'T',
                        'option_text' => 'サイズ=S',
                        'product_bundle_list' => NULL,
                    ],
                    1 => [
                        'shop_no' => 1,
                        'category_no' => 24,
                        'quantity' => 1,
                        'additional_option_values' => NULL,
                        'variant_code' => 'P000000W000A',
                        'product_bundle' => 'F',
                        'prefaid_shipping_fee' => 'P',
                        'attached_file_option' => NULL,
                        'created_date' => '2022-05-13 11:20:32',
                        'product_price' => 1000,
                        'option_price' => -200,
                        'product_bundle_price' => NULL,
                        'product_no' => 22,
                        'option_id' => '000A',
                        'product_bundle_no' => 0,
                        'shipping_type' => 'B',
                        'subscription' => 'F',
                        'subscription_cycle' => NULL,
                        'subscription_shipments_cycle_count' => 0,
                        'basket_product_no' => 3112,
                        'product_name' => 'Basic Product (Custom Variant)',
                        'checked_products' => 'T',
                        'option_text' => 'カラー=Black',
                        'product_bundle_list' => NULL,
                    ],
                ],
                'return_url_base' => 'http://demomall.cafe24shop.com',
                'return_noty_url' => 'http://demomall.cafe24shop.com/Pay/Recv/openpg/PayReceiveCheckoutNoty.php',
                'pg_order_reference_no' => 'pg-order-001',
                'pg_payment_reference_no' => 'pg-payment-001',
                'pg_refund_reference_no' => NULL,
                'paid_amount' => NULL,
                'refund_amount' => NULL,
            ],
        ],
        'async-refund'            => [
            'cancel-20220519-0000029'  => [
                'order_id' => '20220519-0000029',
                'mall_name' => 'demomall',
                'partner_id' => 'demomall:0:demo-app-public-key',
                'buyer_id' => 'mockbuyer',
                'shop_no' => '1',
                'order_status' => 'paid',
                'currency' => 'PHP',
                'checkout_request_amount' => '3065.00',
                'pg_order_reference_no' => 'pg-order-001',
                'pg_refund_reference_no' => NULL,
                'refund_status' => 'pending',
                'paid_amount' => '3065.00',
                'refund_amount' => NULL,
                'extra_data' => [
                    'pgName'     => 'pg-demo-app',
                    'orderId'    => '20220519-0000029',
                    'LogKey'     => 'PGPy051917134394802320q941c03955e4qt9415',
                    'sRdrLogKey' => 'PGPy051917134394802350q052c25850tq12501e',
                ],
            ],
            'webhook-20220519-0000029' => [
                'order_id' => '20220519-0000029',
                'mall_name' => 'demomall',
                'partner_id' => 'demomall:0:demo-app-public-key',
                'buyer_id' => 'mockbuyer',
                'shop_no' => '1',
                'order_status' => 'paid',
                'checkout_request_amount' => '3065.00',
                'pg_order_reference_no' => 'pg-order-001',
                'pg_refund_reference_no' => 'pg-refund-001',
                'refund_status' => 'pending',
                'currency' => 'PHP',
                'paid_amount' => '3065.00',
                'request_refund_amount' => '3065.00',
                'refund_amount' => NULL,
                'cancel_noty_url' => 'https://demomall.cafe24shop.com/Pay/Recv/openpg/PayReceiveCancelNoty.php',
                'extra_data' => [
                    'pgName'     => 'pg-demo-app',
                    'orderId'    => '20220519-0000029',
                    'LogKey'     => 'PGPy051917134394802320q941c03955e4qt9415',
                    'sRdrLogKey' => 'PGPy051917134394802350q052c25850tq12501e',
                ],
            ]
        ],
        'sync-refund'             => [
            '20220519-0000029' => [
                'order_id' => '20220519-0000029',
                'mall_name' => 'ectmtjpq001',
                'partner_id' => 'ectmtjpq001:0:demo-app-public-key',
                'buyer_id' => 'ectmtphq004',
                'shop_no' => '1',
                'order_status' => 'paid',
                'checkout_request_amount' => '3065.00',
                'pg_order_reference_no' => 'pg-order-001',
                'pg_refund_reference_no' => 'pg-refund-001',
                'currency' => 'PHP',
                'paid_amount' => '3065.00',
                'request_refund_amount' =>  NULL,
                'refund_amount' => NULL,
                'refund_status' => NULL,
                'cancel_noty_url' => NULL,
                'extra_data' => [
                    'pgName'     => 'pg-demo-app',
                    'orderId'    => '20220519-0000029',
                    'LogKey'     => 'PGPy051917134394802320q941c03955e4qt9415',
                    'sRdrLogKey' => 'PGPy051917134394802350q052c25850tq12501e',
                ],
            ]
        ]
    ];

    public function getOrder($sOrderId)
    {
        if (isset(self::MOCK_ORDER_DATA[$this->sModule][$sOrderId]) === false) {
            return null;
        }
        return self::MOCK_ORDER_DATA[$this->sModule][$sOrderId];
    }

    public function storeOrder($sOrderId, $aOrder)
    {
        if ($this->sModule === 'sync-checkout') {
            return $this->sModule . ':' . $sOrderId;
        }
        return true;
    }

    public function updateOrder($sOrderId, $aUpdate)
    {
        return true;
    }
}