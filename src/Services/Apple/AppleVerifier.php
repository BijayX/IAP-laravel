<?php

namespace Bijay\Iap\Services\Apple;

use Bijay\Iap\Contracts\VerifierContract;
use Bijay\Iap\DTO\VerificationResult;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class AppleVerifier implements VerifierContract
{
    protected Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 30,
            'connect_timeout' => 10,
        ]);
    }

    /**
     * Verify an Apple receipt.
     *
     * @param array $payload Expected keys: 'receipt_data', 'password' (optional)
     * @return VerificationResult
     */
    public function verify(array $payload): VerificationResult
    {
        $receiptData = $payload['receipt_data'] ?? null;
        $password = $payload['password'] ?? config('iap.apple.shared_secret');

        if (!$receiptData) {
            return new VerificationResult(
                valid: false,
                status: 'invalid',
                expiresAt: null,
                platform: 'ios',
                productId: '',
                originalTransactionId: '',
                rawData: ['error' => 'Missing receipt_data']
            );
        }

        try {
            $response = $this->verifyReceipt($receiptData, $password, false);

            // Handle sandbox fallback (status 21007)
            if (isset($response['status']) && $response['status'] === 21007) {
                $response = $this->verifyReceipt($receiptData, $password, true);
            }

            return $this->parseResponse($response);
        } catch (\Exception $e) {
            Log::error('Apple receipt verification failed', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            return new VerificationResult(
                valid: false,
                status: 'error',
                expiresAt: null,
                platform: 'ios',
                productId: '',
                originalTransactionId: '',
                rawData: ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Verify receipt with Apple's API.
     */
    protected function verifyReceipt(string $receiptData, ?string $password, bool $sandbox): array
    {
        $url = $sandbox
            ? config('iap.apple.sandbox_url')
            : config('iap.apple.verify_receipt_url');

        $response = $this->client->post($url, [
            'json' => [
                'receipt-data' => $receiptData,
                'password' => $password,
                'exclude-old-transactions' => false,
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Parse Apple's verification response.
     */
    protected function parseResponse(array $response): VerificationResult
    {
        // Status 0 means success
        if (($response['status'] ?? -1) !== 0) {
            return new VerificationResult(
                valid: false,
                status: 'invalid',
                expiresAt: null,
                platform: 'ios',
                productId: '',
                originalTransactionId: '',
                rawData: $response
            );
        }

        $receipt = $response['receipt'] ?? [];
        $latestReceiptInfo = $response['latest_receipt_info'] ?? [];
        $pendingRenewalInfo = $response['pending_renewal_info'] ?? [];

        // Get the latest transaction (most recent subscription)
        $latestTransaction = $this->getLatestTransaction($latestReceiptInfo);

        if (!$latestTransaction) {
            // For non-subscription purchases, use receipt data
            $inApp = $receipt['in_app'] ?? [];
            $latestTransaction = !empty($inApp) ? end($inApp) : null;
        }

        if (!$latestTransaction) {
            return new VerificationResult(
                valid: false,
                status: 'invalid',
                expiresAt: null,
                platform: 'ios',
                productId: '',
                originalTransactionId: '',
                rawData: $response
            );
        }

        $productId = $latestTransaction['product_id'] ?? '';
        $originalTransactionId = $latestTransaction['original_transaction_id'] ?? $latestTransaction['transaction_id'] ?? '';
        
        // Parse expiry date
        $expiresAt = null;
        if (isset($latestTransaction['expires_date_ms'])) {
            $expiresAt = Carbon::createFromTimestampMs((int) $latestTransaction['expires_date_ms']);
        } elseif (isset($latestTransaction['expires_date'])) {
            $expiresAt = Carbon::parse($latestTransaction['expires_date']);
        }

        // Determine status
        $status = 'active';
        if ($expiresAt) {
            if ($expiresAt->isPast()) {
                $status = 'expired';
            }
        } else {
            // For non-subscription purchases, check if it's a consumable
            // If there's no expiry, it's a one-time purchase
            $status = 'active';
        }

        // Check cancellation status
        $cancellationDate = $latestTransaction['cancellation_date_ms'] ?? null;
        if ($cancellationDate) {
            $status = 'cancelled';
        }

        // Check pending renewal info for auto-renew status
        $autoRenewStatus = $this->getAutoRenewStatus($pendingRenewalInfo, $productId);
        if ($autoRenewStatus === 'off') {
            // Subscription won't auto-renew but might still be active
            if ($expiresAt && $expiresAt->isPast()) {
                $status = 'expired';
            }
        }

        return new VerificationResult(
            valid: true,
            status: $status,
            expiresAt: $expiresAt,
            platform: 'ios',
            productId: $productId,
            originalTransactionId: $originalTransactionId,
            rawData: $response
        );
    }

    /**
     * Get the latest transaction from receipt info.
     */
    protected function getLatestTransaction(array $latestReceiptInfo): ?array
    {
        if (empty($latestReceiptInfo)) {
            return null;
        }

        // Sort by expires_date_ms descending to get the latest
        usort($latestReceiptInfo, function ($a, $b) {
            $expiresA = $a['expires_date_ms'] ?? 0;
            $expiresB = $b['expires_date_ms'] ?? 0;
            return $expiresB <=> $expiresA;
        });

        return $latestReceiptInfo[0];
    }

    /**
     * Get auto-renew status for a product.
     */
    protected function getAutoRenewStatus(array $pendingRenewalInfo, string $productId): ?string
    {
        foreach ($pendingRenewalInfo as $info) {
            if (($info['product_id'] ?? '') === $productId) {
                return $info['auto_renew_status'] ?? null;
            }
        }

        return null;
    }
}

