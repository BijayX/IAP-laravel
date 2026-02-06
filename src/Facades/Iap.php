<?php

namespace Bijay\Iap\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Bijay\Iap\DTO\VerificationResult verify(string $platform, array $payload)
 * @method static \Bijay\Iap\Contracts\VerifierContract|null getVerifier(string $platform)
 *
 * @see \Bijay\Iap\Services\IapManager
 */
class Iap extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'iap';
    }
}

