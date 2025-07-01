<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Support\DTOs;

use Illuminate\Database\Eloquent\Model;

/**
 * Subscription Creation DTO
 * 
 * Data transfer object for subscription creation operations.
 */
class SubscriptionCreationDTO
{
    public function __construct(
        public readonly Model $subscriber,
        public readonly int $planId,
        public readonly int $pricingId,
        public readonly ?int $trialDays = null
    ) {}

    /**
     * Create from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            subscriber: $data['subscriber'],
            planId: $data['plan_id'],
            pricingId: $data['pricing_id'],
            trialDays: $data['trial_days'] ?? null
        );
    }

    /**
     * Check if this is a trial subscription
     */
    public function isTrial(): bool
    {
        return $this->trialDays !== null && $this->trialDays > 0;
    }
}
