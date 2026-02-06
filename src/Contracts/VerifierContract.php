<?php

namespace Bijay\Iap\Contracts;

use Bijay\Iap\DTO\VerificationResult;

interface VerifierContract
{
    /**
     * Verify an in-app purchase or subscription.
     *
     * @param array $payload Platform-specific payload data
     * @return VerificationResult
     */
    public function verify(array $payload): VerificationResult;
}

