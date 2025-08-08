<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Traits;

use AhmedEssam\SubSphere\Enums\SubscriptionStatus;
use AhmedEssam\SubSphere\Models\Subscription;
use AhmedEssam\SubSphere\Contracts\SubscriptionServiceContract;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * HasSubscriptionQueries Trait
 * 
 * Provides query methods for subscription-related information.
 * These are read-only methods that return subscription state and data.
 */
trait HasSubscriptionQueries
{
    /**
     * Get all subscriptions for this subscriber
     */
    public function subscriptions(): MorphMany
    {
        return $this->morphMany(Subscription::class, 'subscriber');
    }

    /**
     * Get the active subscription for this subscriber
     * 
     * A subscriber can only have ONE active subscription at a time
     */
    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->whereIn('status', SubscriptionStatus::activeStatuses())
            ->where(function ($query) {
                $query->where('ends_at', '>', now())
                    ->orWhere('grace_ends_at', '>', now())
                    ->orWhereNull('ends_at'); // Lifetime subscriptions
            })
            ->first();
    }

    /**
     * Get the active subscription for this subscriber (alias)
     */
    public function subscription(): ?Subscription
    {
        return $this->activeSubscription();
    }

    /**
     * Check if subscriber has any active subscription
     */
    public function hasActiveSubscription(): bool
    {
        return $this->activeSubscription() !== null;
    }

    /**
     * Check if subscriber is currently in trial period
     */
    public function isOnTrial(): bool
    {
        $subscription = $this->activeSubscription();

        return $subscription !== null
            && $subscription->status === SubscriptionStatus::TRIAL
            && $subscription->trial_ends_at !== null
            && $subscription->trial_ends_at->isFuture();
    }

    /**
     * Check if subscriber is in grace period after subscription expiry
     */
    public function isInGracePeriod(): bool
    {
        $subscription = $this->activeSubscription();

        return $subscription !== null
            && $subscription->grace_ends_at !== null
            && $subscription->grace_ends_at->isFuture()
            && ($subscription->ends_at === null || $subscription->ends_at->isPast());
    }

    /**
     * Check if subscriber is subscribed to a specific plan
     */
    public function isSubscribedTo(int $planId): bool
    {
        $subscription = $this->activeSubscription();

        return $subscription !== null && $subscription->plan_id === $planId;
    }

    /**
     * Get current subscription status
     */
    public function subscriptionStatus(): ?SubscriptionStatus
    {
        $subscription = $this->activeSubscription();

        return $subscription?->status;
    }

    /**
     * Get days remaining in current subscription
     */
    public function daysRemaining(): ?int
    {
        $subscription = $this->activeSubscription();

        if ($subscription === null || $subscription->ends_at === null) {
            return null; // Lifetime or no subscription
        }

        $endDate = $this->isInGracePeriod()
            ? $subscription->grace_ends_at
            : $subscription->ends_at;

        return $endDate ? max(0, now()->diffInDays($endDate, false)) : null;
    }

    /**
     * Get subscription history for this subscriber
     */
    public function subscriptionHistory()
    {
        return $this->subscriptions()
            ->with(['plan', 'planPricing'])
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc') // Secondary sort by ID for consistency when timestamps are identical
            ->get();
    }

    /**
     * Check if subscriber has any cancelled subscription
     */
    public function hasCancelledSubscription(): bool
    {
        return $this->subscriptions()
            ->where('status', SubscriptionStatus::CANCELED)
            ->exists();
    }

    /**
     * Check if subscriber has any pending subscription
     */
    public function hasPendingSubscription(): bool
    {
        return $this->subscriptions()
            ->where('status', SubscriptionStatus::PENDING)
            ->exists();
    }

    /**
     * Check if subscriber has any trial subscription
     */
    public function hasTrialSubscription(): bool
    {
        return $this->subscriptions()
            ->where('status', SubscriptionStatus::TRIAL)
            ->exists();
    }

    /**
     * Check if subscriber has any inactive subscription
     */
    public function hasInactiveSubscription(): bool
    {
        return $this->subscriptions()
            ->where('status', SubscriptionStatus::INACTIVE)
            ->exists();
    }

    /**
     * Check if subscriber has any expired subscription
     */
    public function hasExpiredSubscription(): bool
    {
        return $this->subscriptions()
            ->where('status', SubscriptionStatus::EXPIRED)
            ->exists();
    }

    /**
     * Check if subscriber has subscriptions with any of the inactive statuses
     */
    public function hasInactiveSubscriptions(): bool
    {
        return $this->subscriptions()
            ->whereIn('status', SubscriptionStatus::inactiveStatuses())
            ->exists();
    }

    /**
     * Get count of subscriptions by status
     */
    public function getSubscriptionCountByStatus(SubscriptionStatus $status): int
    {
        return $this->subscriptions()
            ->where('status', $status)
            ->count();
    }

    /**
     * Get all subscription statuses the subscriber has had
     */
    public function getSubscriptionStatuses(): array
    {
        return $this->subscriptions()
            ->distinct('status')
            ->pluck('status')
            ->toArray();
    }
}
