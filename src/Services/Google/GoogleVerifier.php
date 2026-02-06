<?php

namespace Bijay\Iap\Services\Google;

use Bijay\Iap\Contracts\VerifierContract;
use Bijay\Iap\DTO\VerificationResult;
use Carbon\Carbon;
use Google_Client;
use Google_Service_AndroidPublisher;
use Illuminate\Support\Facades\Log;

class GoogleVerifier implements VerifierContract
{
    protected ?Google_Service_AndroidPublisher $service = null;

    public function __construct()
    {
        $this->initializeClient();
    }

    /**
     * Verify a Google Play purchase or subscription.
     *
     * @param array $payload Expected keys: 'package_name', 'product_id', 'purchase_token'
     * @return VerificationResult
     */
    public function verify(array $payload): VerificationResult
    {
        $packageName = $payload['package_name'] ?? config('iap.google.package_name');
        $productId = $payload['product_id'] ?? '';
        $purchaseToken = $payload['purchase_token'] ?? '';

        if (!$packageName || !$productId || !$purchaseToken) {
            return new VerificationResult(
                valid: false,
                status: 'invalid',
                expiresAt: null,
                platform: 'android',
                productId: $productId,
                originalTransactionId: '',
                rawData: ['error' => 'Missing required fields: package_name, product_id, or purchase_token']
            );
        }

        try {
            // Determine if it's a subscription or one-time purchase
            $isSubscription = $payload['is_subscription'] ?? true;

            if ($isSubscription) {
                return $this->verifySubscription($packageName, $productId, $purchaseToken);
            }

            return $this->verifyPurchase($packageName, $productId, $purchaseToken);
        } catch (\Exception $e) {
            Log::error('Google Play verification failed', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            return new VerificationResult(
                valid: false,
                status: 'error',
                expiresAt: null,
                platform: 'android',
                productId: $productId,
                originalTransactionId: '',
                rawData: ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Verify a subscription.
     */
    protected function verifySubscription(string $packageName, string $productId, string $purchaseToken): VerificationResult
    {
        try {
            $subscription = $this->service->purchases_subscriptions->get(
                $packageName,
                $productId,
                $purchaseToken
            );

            $expiryTimeMillis = $subscription->getExpiryTimeMillis();
            $expiresAt = $expiryTimeMillis ? Carbon::createFromTimestampMs($expiryTimeMillis) : null;

            // Determine status
            $status = 'active';
            $autoRenewing = $subscription->getAutoRenewing() ?? false;

            if ($expiresAt && $expiresAt->isPast()) {
                $status = 'expired';
            }

            if (!$autoRenewing && $expiresAt && $expiresAt->isPast()) {
                $status = 'cancelled';
            }

            // Get order ID as transaction ID
            $orderId = $subscription->getOrderId() ?? $purchaseToken;

            return new VerificationResult(
                valid: true,
                status: $status,
                expiresAt: $expiresAt,
                platform: 'android',
                productId: $productId,
                originalTransactionId: $orderId,
                rawData: [
                    'kind' => $subscription->getKind(),
                    'start_time_millis' => $subscription->getStartTimeMillis(),
                    'expiry_time_millis' => $expiryTimeMillis,
                    'auto_renewing' => $autoRenewing,
                    'price_currency_code' => $subscription->getPriceCurrencyCode(),
                    'price_amount_micros' => $subscription->getPriceAmountMicros(),
                    'country_code' => $subscription->getCountryCode(),
                    'payment_state' => $subscription->getPaymentState(),
                    'order_id' => $orderId,
                ]
            );
        } catch (\Google_Service_Exception $e) {
            Log::error('Google subscription verification error', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return new VerificationResult(
                valid: false,
                status: 'error',
                expiresAt: null,
                platform: 'android',
                productId: $productId,
                originalTransactionId: '',
                rawData: ['error' => $e->getMessage(), 'code' => $e->getCode()]
            );
        }
    }

    /**
     * Verify a one-time purchase.
     */
    protected function verifyPurchase(string $packageName, string $productId, string $purchaseToken): VerificationResult
    {
        try {
            $purchase = $this->service->purchases_products->get(
                $packageName,
                $productId,
                $purchaseToken
            );

            $purchaseState = $purchase->getPurchaseState();
            $consumptionState = $purchase->getConsumptionState();

            // Purchase state: 0 = purchased, 1 = cancelled
            $status = $purchaseState === 0 ? 'active' : 'cancelled';

            // Get order ID as transaction ID
            $orderId = $purchase->getOrderId() ?? $purchaseToken;

            return new VerificationResult(
                valid: $purchaseState === 0,
                status: $status,
                expiresAt: null, // One-time purchases don't expire
                platform: 'android',
                productId: $productId,
                originalTransactionId: $orderId,
                rawData: [
                    'kind' => $purchase->getKind(),
                    'purchase_state' => $purchaseState,
                    'consumption_state' => $consumptionState,
                    'order_id' => $orderId,
                    'purchase_time_millis' => $purchase->getPurchaseTimeMillis(),
                ]
            );
        } catch (\Google_Service_Exception $e) {
            Log::error('Google purchase verification error', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return new VerificationResult(
                valid: false,
                status: 'error',
                expiresAt: null,
                platform: 'android',
                productId: $productId,
                originalTransactionId: '',
                rawData: ['error' => $e->getMessage(), 'code' => $e->getCode()]
            );
        }
    }

    /**
     * Initialize Google API client.
     */
    protected function initializeClient(): void
    {
        $serviceAccountPath = config('iap.google.service_account_path');

        if (!file_exists($serviceAccountPath)) {
            Log::warning('Google service account file not found', [
                'path' => $serviceAccountPath,
            ]);
            return;
        }

        try {
            $client = new Google_Client();
            $client->setAuthConfig($serviceAccountPath);
            $client->addScope(Google_Service_AndroidPublisher::ANDROIDPUBLISHER);

            $this->service = new Google_Service_AndroidPublisher($client);
        } catch (\Exception $e) {
            Log::error('Failed to initialize Google API client', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}

