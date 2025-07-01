<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Support\DTOs;

use Illuminate\Database\Eloquent\Model;

/**
 * Feature Consumption DTO
 * 
 * Data transfer object for feature consumption operations.
 */
class FeatureConsumptionDTO
{
    public function __construct(
        public readonly Model $subscriber,
        public readonly string $featureKey,
        public readonly int $amount = 1
    ) {}

    /**
     * Create from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            subscriber: $data['subscriber'],
            featureKey: $data['feature_key'],
            amount: $data['amount'] ?? 1
        );
    }
}
