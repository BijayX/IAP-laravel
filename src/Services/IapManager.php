<?php

namespace Bijay\Iap\Services;

use Bijay\Iap\Contracts\VerifierContract;
use Bijay\Iap\DTO\VerificationResult;
use Bijay\Iap\Services\Apple\AppleVerifier;
use Bijay\Iap\Services\Google\GoogleVerifier;
use Illuminate\Support\Facades\Log;

class IapManager
{
    protected array $verifiers = [];

    public function __construct()
    {
        $this->verifiers = [
            'ios' => new AppleVerifier(),
            'apple' => new AppleVerifier(),
            'android' => new GoogleVerifier(),
            'google' => new GoogleVerifier(),
        ];
    }

    /**
     * Verify an in-app purchase or subscription.
     *
     * @param string $platform Platform identifier: 'ios', 'apple', 'android', or 'google'
     * @param array $payload Platform-specific payload data
     * @return VerificationResult
     */
    public function verify(string $platform, array $payload): VerificationResult
    {
        $platform = strtolower($platform);

        if (!isset($this->verifiers[$platform])) {
            Log::error('Unsupported platform for IAP verification', [
                'platform' => $platform,
            ]);

            return new VerificationResult(
                valid: false,
                status: 'error',
                expiresAt: null,
                platform: $platform,
                productId: '',
                originalTransactionId: '',
                rawData: ['error' => "Unsupported platform: {$platform}"]
            );
        }

        $verifier = $this->verifiers[$platform];

        return $verifier->verify($payload);
    }

    /**
     * Get a verifier instance for a platform.
     *
     * @param string $platform
     * @return VerifierContract|null
     */
    public function getVerifier(string $platform): ?VerifierContract
    {
        $platform = strtolower($platform);

        return $this->verifiers[$platform] ?? null;
    }
}

