<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Actions;

use AhmedEssam\SubSphere\Models\Subscription;
use AhmedEssam\SubSphere\Events\SubscriptionRenewed;

/**
 * RenewSubscriptionAction
 * 
 * Handles subscription renewal by extending the subscription period.
 * Can be used for both manual renewals and automatic renewals.
 */
class RenewSubscriptionAction extends BaseAction
{
    public function __construct(
        private readonly Subscription $subscription,
        private readonly bool $isAutoRenewal = false
    ) {}

    /**
     * Validate that subscription can be renewed
     */
    protected function validate(): void
    {
        if (!$this->subscription->canRenew()) {
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.cannot_renew_status', [
                'status' => $this->subscription->status->value
            ]));
        }

        // Validate that the plan is still active
        $validator = app(\AhmedEssam\SubSphere\Services\SubscriptionValidator::class);
        $validator->validatePlanAvailability($this->subscription->plan);

        // Validate that the pricing is still available
        $validator->validatePricingAvailability($this->subscription->planPricing);
    }

    /**
     * Execute subscription renewal
     */
    public function execute(): Subscription
    {
        // Get renewal duration from pricing
        $durationDays = $this->subscription->planPricing->duration_in_days;

        // Extend subscription period
        $this->subscription->extend($durationDays);
        $this->subscription->markAsActive();
        $this->subscription->save();

        // Dispatch renewal event
        event(new SubscriptionRenewed(
            $this->subscription,
            $this->subscription->subscriber,
            $this->isAutoRenewal
        ));

        return $this->subscription;
    }

    /**
     * Static factory method for manual renewal
     */
    public static function forManualRenewal(Subscription $subscription): self
    {
        return new self($subscription, false);
    }

    /**
     * Static factory method for auto renewal
     */
    public static function forAutoRenewal(Subscription $subscription): self
    {
        return new self($subscription, true);
    }

    /**
     * Get the new end date after renewal
     */
    public function getNewEndDate(): ?\DateTime
    {
        if ($this->subscription->ends_at === null) {
            return null; // Lifetime subscription
        }

        $durationDays = $this->subscription->planPricing->duration_in_days;
        return $this->subscription->ends_at->copy()->addDays($durationDays);
    }
}
