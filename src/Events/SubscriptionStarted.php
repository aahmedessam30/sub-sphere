<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Events;

use AhmedEssam\SubSphere\Models\Subscription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * SubscriptionStarted Event
 * 
 * Fired when a new subscription is created and becomes active.
 * Includes both paid subscriptions and trial subscriptions.
 */
class SubscriptionStarted implements ShouldQueue
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly Model $subscriber,
        public readonly bool $isTrial = false
    ) {}

    /**
     * Get the plan associated with this subscription
     */
    public function getPlan(): \AhmedEssam\SubSphere\Models\Plan
    {
        return $this->subscription->plan;
    }

    /**
     * Get the pricing associated with this subscription
     */
    public function getPricing(): \AhmedEssam\SubSphere\Models\PlanPricing
    {
        return $this->subscription->pricing;
    }
}
