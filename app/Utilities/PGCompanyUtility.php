<?php

namespace App\Utilities;

/**
 * Utility class for used PG Company API Guzzle requests and other related methods.
 * Note: The methods in this class are made for demo purposes only
 */
class PGCompanyUtility
{

    /**
     * The order status from this list are not real
     * Refer to the PG Company's various status
     */
    const PG_STATUS_TO_APP_STATUS_MAPPING = [
        'P'  => 'pending',
        'F'  => 'failed',
        'C'  => 'cancelled',
        'S'  => 'paid',
        'R'  => 'refunded',
        'RP' => 'refund_pending',
        'RX' => 'refund_rejected',
        'E'  => 'expired',
        'A'  => 'authorized'
    ];

    const PG_SUPPORTED_CURRENCY = ['JPY', 'PHP', 'USD'];

    const PG_PUBLIC_KEY = 'demo-app-public-key';

    const PG_PRIVATE_KEY = 'demo-app-secret-key';

    private $sSecretKey;

    private $sPublicKey;

    private $sModule;

    public function __construct($sModule)
    {
        $this->sModule = $sModule;
    }

    public function setPGClient($aConfig)
    {
        $this->sPublicKey = $aConfig['public_key'];
        $this->sSecretKey = $aConfig['secret_key'];
    }

    public  function createOrderCheckout($aCheckoutRequest)
    {
        if ($this->sPublicKey !== self::PG_PUBLIC_KEY || $this->sSecretKey !== self::PG_PRIVATE_KEY) {
            return [
                'code'    => 401,
                'message' => 'Invalid credentials'
            ];
        }

        // Mock Guzzle responses for the various checkout requests
        if ($this->sModule === 'asynchronous-checkout') {
            return [
                'mData' => [
                    'code'          => 200,
                    'redirect_uri'  => 'https://dummy-pg/checkout?order_id=0000-0000-0001',
                    'reference_no' => 'pg-order-001',
                ]
            ];
        }
        if ($this->sModule === 'synchronous-checkout') {
            return [
                'mData' => [
                    'code'          => 200,
                    'redirect_uri'  => 'https://dummy-pg/checkout?order_id=0000-0000-0001',
                ]
            ];
        }
        if ($this->sModule === 'asynchronous-external-checkout') {
            return [
                'mData' => [
                    'code'         => 200,
                    'redirect_uri' => 'https://pg-demo-app.local.com/api/asynchronous-external-checkout/review/order?mall_id=ectmtjpq001&order_id=20220513-0000079',
                    'reference_no' => 'pg-order-001',
                    'order_status' => 'P',
                    'signature'    => 'mock-signature-key',
                    'pubic_key'    => 'demo-app-public-key'
                ]
            ];
        }
        if ($this->sModule === 'synchronous-external-checkout') {
            return [
                'mData' => [
                    'code'         => 200,
                    'redirect_uri' => 'https://pg-demo-app.local.com/api/asynchronous-external-checkout/review/order?mall_id=ectmtjpq001&order_id=20220513-0000079',
                    'reference_no' => 'pg-order-001',
                    'order_status' => 'P',
                    'signature'    => 'mock-signature-key',
                    'pubic_key'    => 'demo-app-public-key'
                ]
            ];
        }

        return [
            'code'    => 400,
            'message' => 'Invalid request'
        ];
    }

    public function createRefund($sOrderCode, $aRefundRequest)
    {
        if ($this->sPublicKey !== self::PG_PUBLIC_KEY || $this->sSecretKey !== self::PG_PRIVATE_KEY) {
            return [
                'code'    => 401,
                'message' => 'Invalid credentials'
            ];
        }

        // Mock Guzzle responses for the various cancellation/refund requests
        if ($this->sModule === 'asynchronous-refund') {
            if ($sOrderCode === 'pg-order-001') {
                return [
                    'mData' => [
                        'code' => 200,
                        'order_code'  => $sOrderCode,
                        'refund_code'   => 'pg-refund-001',
                        'refund_status'          => 'RP',
                        'request_refund_amount' => '3065.00',
                        'currency'              => 'PHP'
                    ]
                ];
            }
        }
        if ($this->sModule === 'synchronous-refund') {
            if ($sOrderCode === 'pg-order-001') {
                return [
                    'mData' => [
                        'code' => 200,
                        'order_code'  => $sOrderCode,
                        'refund_code'   => 'pg-refund-001',
                        'refund_status'         => 'R',
                        'request_refund_amount' => '0.00',
                        'refunded_amount'       => '0.00',
                        'currency'              => 'PHP',
                        'message'               => 'Successfully refunded'
                    ]
                ];
            }
        }

        return [
            'code'    => 401,
            'message' => 'Invalid request'
        ];
    }

    public function getOrder($sOrderId)
    {
        if ($this->sPublicKey !== self::PG_PUBLIC_KEY || $this->sSecretKey !== self::PG_PRIVATE_KEY) {
            return [
                'code'    => 401,
                'message' => 'Invalid credentials'
            ];
        }

        // Mock Guzzle responses for the various fetch requests
        if ($this->sModule === 'asynchronous-external-checkout') {
           if ($sOrderId === 'pg-order-001') {
               return ['mData' => [
                   'code'                 => 200,
                   'reference_no'         => 'pg-order-001',
                   'payment_reference_no' => 'pg-pay-001',
                   'payment_status'       => 'A', //authorized for payment
                   'order_status'         => 'P', //order still not complete 'pending'
                   'preference'           => 'icash',
                   'shipping_address'     => [
                       'postal_code'  => '1550',
                       'country_code' => 'PH',
                       'city'         => 'Mandaluyong',
                       'state'        => 'NCR',
                       'name'         => 'Tom Cruise',
                       'add_1'        => 'address1',
                       'add_2'        => 'address2',
                       'add_3'        => 'address3',
                       'country'      => 'Philippines',
                       'district'     => 'Bagong Silang',
                       'phone_number' => '+639000000001',
                   ]
               ]
               ];
           }
        }
        if ($this->sModule === 'synchronous-external-checkout') {
            if ($sOrderId === 'pg-order-001') {
                return ['mData' => [
                    'code'                 => 200,
                    'reference_no'         => 'pg-order-001',
                    'payment_reference_no' => 'pg-pay-001',
                    'payment_status'       => 'S',
                    'order_status'         => 'S',
                    'paid_amount'          => '2200.00',
                    'preference'           => 'icash',
                    'shipping_address'     => [
                        'postal_code'  => '1550',
                        'country_code' => 'PH',
                        'city'         => 'Mandaluyong',
                        'state'        => 'NCR',
                        'name'         => 'Tom Cruise',
                        'add_1'        => 'address1',
                        'add_2'        => 'address2',
                        'add_3'        => 'address3',
                        'country'      => 'Philippines',
                        'district'     => 'Bagong Silang',
                        'phone_number' => '+639000000001',
                    ]
                ]
                ];
            }
        }
        if ($this->sModule === 'synchronous-checkout') {
            if ($sOrderId === 'pg-order-001') {
                return ['mData' => [
                    'code'                 => 200,
                    'reference_no'         => 'pg-order-001',
                    'order_status'         => 'S',
                    'preference'           => 'icash',
                    'paid_amount'          => '3065.00',
                    'shipping_address'     => [
                        'postal_code'  => '1550',
                        'country_code' => 'PH',
                        'city'         => 'Mandaluyong',
                        'state'        => 'NCR',
                        'name'         => 'Tom Cruise',
                        'add_1'        => 'address1',
                        'add_2'        => 'address2',
                        'add_3'        => 'address3',
                        'country'      => 'Philippines',
                        'district'     => 'Bagong Silang',
                        'phone_number' => '+639000000001',
                    ]
                ]
                ];
            }
        }

        return [
            'code'  => 404,
            'order' => 'Order not found'
        ];
    }

    public function payOrder($sOrderNo, $aPaymentData) {
        if ($this->sPublicKey !== self::PG_PUBLIC_KEY || $this->sSecretKey !== self::PG_PRIVATE_KEY) {
            return [
                'code'    => 401,
                'message' => 'Invalid credentials'
            ];
        }

        // Mock Guzzle responses for the various external checkout requests
        if ($this->sModule === 'asynchronous-external-checkout') {
            if ($sOrderNo === 'pg-order-001') {
                return [
                    'mData' => [
                        'code' => 200,
                        'payment_reference_no' => $aPaymentData['payment_reference_no'],
                        'order_status'         => 'S',
                        'request_order_amount' => '8720.00',
                        'redirect_url'         => 'https://dummy-pg/payment?order_id=pg-order-001'
                    ]
                ];
            }
        }
        if ($this->sModule === 'synchronous-external-checkout') {
            if ($sOrderNo === 'pg-order-001') {
                return [
                    'mData' => [
                        'code' => 200,
                        'payment_reference_no' => $aPaymentData['payment_reference_no'],
                        'redirect_url'         => 'https://dummy-pg/payment?order_id=pg-order-001'
                    ]
                ];
            }
        }

        return [
            'code'  => 404,
            'order' => 'Order not found'
        ];
    }
}
