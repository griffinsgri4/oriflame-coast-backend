<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MpesaService
{
    private function baseUrl(): string
    {
        $env = (string) config('mpesa.environment', 'sandbox');
        return $env === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
    }

    private function isConfigured(): bool
    {
        if (!config('mpesa.enabled')) {
            return false;
        }

        return (bool) (
            config('mpesa.consumer_key')
            && config('mpesa.consumer_secret')
            && config('mpesa.shortcode')
            && config('mpesa.passkey')
            && config('mpesa.callback_url')
        );
    }

    public function normalizePhone(string $phone): string
    {
        $p = preg_replace('/\s+/', '', $phone);
        $p = ltrim((string) $p, '+');
        if (Str::startsWith($p, '0')) {
            $p = '254' . substr($p, 1);
        }
        if (Str::startsWith($p, '7') || Str::startsWith($p, '1')) {
            $p = '254' . $p;
        }
        return $p;
    }

    private function accessToken(): string
    {
        $key = (string) config('mpesa.consumer_key');
        $secret = (string) config('mpesa.consumer_secret');

        $res = Http::timeout(20)
            ->withBasicAuth($key, $secret)
            ->get($this->baseUrl() . '/oauth/v1/generate', ['grant_type' => 'client_credentials']);

        if (!$res->ok()) {
            throw new \RuntimeException('Failed to get M-Pesa access token');
        }

        $token = (string) ($res->json('access_token') ?? '');
        if ($token === '') {
            throw new \RuntimeException('M-Pesa access token missing');
        }

        return $token;
    }

    public function stkPush(array $payload): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('M-Pesa is not configured');
        }

        $timestamp = now()->format('YmdHis');
        $shortcode = (string) config('mpesa.shortcode');
        $passkey = (string) config('mpesa.passkey');
        $password = base64_encode($shortcode . $passkey . $timestamp);

        $token = $this->accessToken();

        $body = [
            'BusinessShortCode' => $shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => (int) $payload['amount'],
            'PartyA' => $payload['phone'],
            'PartyB' => $shortcode,
            'PhoneNumber' => $payload['phone'],
            'CallBackURL' => (string) config('mpesa.callback_url'),
            'AccountReference' => (string) ($payload['account_reference'] ?? config('mpesa.account_reference')),
            'TransactionDesc' => (string) ($payload['transaction_desc'] ?? config('mpesa.transaction_desc')),
        ];

        $res = Http::timeout(25)
            ->withToken($token)
            ->acceptJson()
            ->asJson()
            ->post($this->baseUrl() . '/mpesa/stkpush/v1/processrequest', $body);

        if (!$res->ok()) {
            throw new \RuntimeException('M-Pesa STK push failed');
        }

        return [
            'request' => $body,
            'response' => $res->json(),
        ];
    }
}

