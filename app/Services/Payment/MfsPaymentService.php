<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MfsPaymentService
{
    /**
     * Initialize bKash Payment URL
     */
    public function initiateBkashPayment(Order $order): array
    {
        $appKey = Setting::get('bkash_app_key', env('BKASH_APP_KEY', ''));
        $appSecret = Setting::get('bkash_app_secret', env('BKASH_APP_SECRET', ''));
        $username = Setting::get('bkash_username', env('BKASH_USERNAME', ''));
        $password = Setting::get('bkash_password', env('BKASH_PASSWORD', ''));
        $isSandbox = Setting::get('bkash_sandbox', env('BKASH_SANDBOX', true));

        $baseUrl = $isSandbox
            ? 'https://tokenized.sandbox.bka.sh/v2.0'
            : 'https://tokenized.pay.bka.sh/v2.0';

        if (empty($appKey) || empty($appSecret)) {
            return [
                'success' => false,
                'message' => 'bKash credentials not configured. Please update in payment settings.',
            ];
        }

        try {
            // 1. Grant Token
            $tokenRes = Http::withHeaders([
                'username' => $username,
                'password' => $password,
            ])->post("{$baseUrl}/tokenized/checkout/token/grant", [
                'app_key' => $appKey,
                'app_secret' => $appSecret,
            ]);

            if (!$tokenRes->successful()) {
                return ['success' => false, 'message' => 'bKash token generation failed.'];
            }

            $idToken = $tokenRes->json()['id_token'] ?? null;

            // 2. Create Payment
            $createRes = Http::withHeaders([
                'Authorization' => $idToken,
                'X-APP-Key' => $appKey,
            ])->post("{$baseUrl}/tokenized/checkout/create", [
                'mode' => '0011',
                'payerReference' => $order->user->phone ?? '01700000000',
                'callbackURL' => route('payment.bkash.callback'),
                'amount' => (string) $order->total,
                'currency' => 'BDT',
                'intent' => 'sale',
                'merchantInvoiceNumber' => $order->order_number,
            ]);

            if ($createRes->successful() && isset($createRes->json()['bkashURL'])) {
                return [
                    'success' => true,
                    'paymentID' => $createRes->json()['paymentID'],
                    'redirectURL' => $createRes->json()['bkashURL'],
                ];
            }

            return ['success' => false, 'message' => $createRes->json()['statusMessage'] ?? 'Payment creation failed.'];
        } catch (\Throwable $e) {
            Log::error('bKash Payment Exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Execute bKash Callback
     */
    public function executeBkashPayment(string $paymentID): array
    {
        $appKey = Setting::get('bkash_app_key', env('BKASH_APP_KEY', ''));
        $isSandbox = Setting::get('bkash_sandbox', env('BKASH_SANDBOX', true));
        $baseUrl = $isSandbox ? 'https://tokenized.sandbox.bka.sh/v2.0' : 'https://tokenized.pay.bka.sh/v2.0';

        // Retrieve token from session or cache
        $idToken = session('bkash_id_token');

        try {
            $response = Http::withHeaders([
                'Authorization' => $idToken,
                'X-APP-Key' => $appKey,
            ])->post("{$baseUrl}/tokenized/checkout/execute", [
                'paymentID' => $paymentID,
            ]);

            $data = $response->json();

            if (isset($data['statusCode']) && $data['statusCode'] === '0000') {
                return [
                    'success' => true,
                    'trxID' => $data['trxID'] ?? '',
                    'invoice' => $data['merchantInvoiceNumber'] ?? '',
                    'amount' => $data['amount'] ?? 0,
                ];
            }

            return ['success' => false, 'message' => $data['statusMessage'] ?? 'Verification failed'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Initialize Nagad Payment URL
     */
    public function initiateNagadPayment(Order $order): array
    {
        $merchantId = Setting::get('nagad_merchant_id', env('NAGAD_MERCHANT_ID', ''));

        if (empty($merchantId)) {
            return [
                'success' => false,
                'message' => 'Nagad credentials not configured.',
            ];
        }

        // Return structured payment initiation parameters
        return [
            'success' => true,
            'gateway' => 'nagad',
            'order_number' => $order->order_number,
            'amount' => $order->total,
            'callback_url' => route('payment.nagad.callback'),
        ];
    }
}
