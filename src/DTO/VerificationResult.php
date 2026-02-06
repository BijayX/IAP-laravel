<?php

namespace Bijay\Iap\DTO;

use Carbon\Carbon;

class VerificationResult
{
    public function __construct(
        public readonly bool $valid,
        public readonly string $status,
        public readonly ?Carbon $expiresAt,
        public readonly string $platform,
        public readonly string $productId,
        public readonly string $originalTransactionId,
        public readonly array $rawData = []
    ) {
    }

    /**
     * Check if the subscription is active.
     */
    public function isActive(): bool
    {
        if (!$this->valid) {
            return false;
        }

        if ($this->expiresAt === null) {
            return $this->status === 'active';
        }

        return $this->status === 'active' && $this->expiresAt->isFuture();
    }

    /**
     * Check if the subscription has expired.
     */
    public function isExpired(): bool
    {
        if (!$this->valid || $this->expiresAt === null) {
            return false;
        }

        return $this->expiresAt->isPast();
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'status' => $this->status,
            'expires_at' => $this->expiresAt?->toIso8601String(),
            'platform' => $this->platform,
            'product_id' => $this->productId,
            'original_transaction_id' => $this->originalTransactionId,
            'raw_data' => $this->rawData,
        ];
    }
}

