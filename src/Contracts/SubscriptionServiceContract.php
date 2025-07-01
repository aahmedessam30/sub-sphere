<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Contracts;

use AhmedEssam\SubSphere\Models\Plan;
use AhmedEssam\SubSphere\Models\Subscription;
use Illuminate\Database\Eloquent\Model;

/**
 * Subscription Service Contract
 * 
 * Defines the interface for subscription lifecycle management.
 * Implementations should handle all business logic for subscriptions.
 */
interface SubscriptionServiceContract
{
    /**
     * Subscribe a user to a plan with specific pricing
     */
    public function subscribe(Model $subscriber, int $planId, int $pricingId, ?int $trialDays = null): Subscription;

    /**
     * Start a trial subscription for a user
     */
    public function startTrial(Model $subscriber, int $planId, int $durationInDays): Subscription;

    /**
     * Renew an existing subscription
     */
    public function renew(Subscription $subscription): Subscription;

    /**
     * Cancel a subscription (keeps access until period ends)
     */
    public function cancel(Subscription $subscription): Subscription;

    /**
     * Resume a canceled subscription
     */
    public function resume(Subscription $subscription): Subscription;

    /**
     * Immediately expire a subscription
     */
    public function expire(Subscription $subscription): Subscription;

    /**
     * Check if user has access to a specific feature
     */
    public function hasFeature(Model $subscriber, string $key): bool;

    /**
     * Get the value of a feature for a user
     */
    public function getFeatureValue(Model $subscriber, string $key): mixed;

    /**
     * Consume usage for a feature
     */
    public function consumeFeature(Model $subscriber, string $key, int $amount = 1): bool;

    /**
     * Reset feature usage for a user
     */
    public function resetFeature(Model $subscriber, string $key): bool;
}