<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Actions;

use AhmedEssam\SubSphere\Models\Subscription;
use AhmedEssam\SubSphere\Events\SubscriptionExpired;

/**
 * ExpireSubscriptionAction
 * 
 * Handles immediate expiration of subscriptions.
 * Used for forced expiration or when grace period ends.
 */
class ExpireSubscriptionAction extends BaseAction
{
    public function __construct(
        private readonly Subscription $subscription
    ) {}

    /**
     * Validate subscription can be expired
     */
    protected function validate(): void
    {
        // Check if subscription is already expired
        if ($this->subscription->status === \AhmedEssam\SubSphere\Enums\SubscriptionStatus::EXPIRED) {
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.subscription_already_expired'));
        }

        // Prevent expiration of lifetime subscriptions
        if ($this->subscription->isLifetime()) {
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.cannot_expire_lifetime'));
        }

        // Validate business rules about forced expiration
        // Ensure we're not in a conflicting state (e.g., already being canceled)
        if ($this->subscription->status === \AhmedEssam\SubSphere\Enums\SubscriptionStatus::CANCELED) {
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.cannot_expire_canceled'));
        }

        // Additional business rule: validate that forced expiration is allowed
        // if this is not a natural expiration (subscription should have an end date)
        if (!$this->subscription->ends_at && !$this->subscription->isInGracePeriod()) {
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.cannot_force_expire'));
        }
    }

    /**
     * Execute subscription expiration
     */
    public function execute(): Subscription
    {
        $wasInGracePeriod = $this->subscription->isInGracePeriod();

        // Mark subscription as expired
        $this->subscription->markAsExpired();
        $this->subscription->save();

        // Dispatch expiration event
        event(new SubscriptionExpired(
            $this->subscription,
            $this->subscription->subscriber,
            $wasInGracePeriod
        ));

        return $this->subscription;
    }

    /**
     * Static factory method for convenience
     */
    public static function for(Subscription $subscription): self
    {
        return new self($subscription);
    }

    /**
     * Check if subscription was in grace period before expiration
     */
    public function wasInGracePeriod(): bool
    {
        return $this->subscription->isInGracePeriod();
    }
}
