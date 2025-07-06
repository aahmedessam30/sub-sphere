<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Traits;

use AhmedEssam\SubSphere\Enums\SubscriptionStatus;
use AhmedEssam\SubSphere\Models\Plan;
use AhmedEssam\SubSphere\Models\PlanPricing;
use AhmedEssam\SubSphere\Models\Subscription;
use AhmedEssam\SubSphere\Contracts\SubscriptionServiceContract;
use AhmedEssam\SubSphere\Exceptions\CouldNotStartSubscriptionException;
use AhmedEssam\SubSphere\Events\SubscriptionStarted;
use AhmedEssam\SubSphere\Events\TrialStarted;
use AhmedEssam\SubSphere\Events\SubscriptionChanged;
use AhmedEssam\SubSphere\Actions\ChangeSubscriptionPlanAction;
use AhmedEssam\SubSphere\Actions\CancelSubscriptionAction;
use AhmedEssam\SubSphere\Actions\ResumeSubscriptionAction;
use AhmedEssam\SubSphere\Actions\RenewSubscriptionAction;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * HasSubscriptions Trait
 * 
 * Add subscription capabilities to any model (User, Team, Organization, etc.)
 * Provides relationship and convenience methods for subscription management.
 */
trait HasSubscriptions
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
     * Get the active subscription for this subscriber
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
     * Check if subscriber has access to a specific feature
     */
    public function hasFeature(string $featureKey): bool
    {
        return app(SubscriptionServiceContract::class)->hasFeature($this, $featureKey);
    }

    /**
     * Get the value of a feature for this subscriber
     */
    public function getFeatureValue(string $featureKey): mixed
    {
        return app(SubscriptionServiceContract::class)->getFeatureValue($this, $featureKey);
    }

    /**
     * Consume feature usage for this subscriber
     */
    public function consumeFeature(string $featureKey, int $amount = 1): bool
    {
        return app(SubscriptionServiceContract::class)->consumeFeature($this, $featureKey, $amount);
    }

    /**
     * Subscribe this subscriber to a plan
     */
    public function subscribe(int $planId, int $pricingId, ?int $trialDays = null): Subscription
    {
        // Ensure no active subscription exists (business rule)
        if ($this->hasActiveSubscription()) {
            throw CouldNotStartSubscriptionException::alreadySubscribed();
        }

        $subscription = app(SubscriptionServiceContract::class)->subscribe($this, $planId, $pricingId, $trialDays);

        // Dispatch subscription started event
        $isTrial = $trialDays !== null && $trialDays > 0;
        event(new SubscriptionStarted($subscription, $this, $isTrial));

        return $subscription;
    }

    /**
     * Start a trial subscription for this subscriber
     */
    public function startTrial(int $planId, int $trialDays): Subscription
    {
        // Ensure no active subscription exists
        if ($this->hasActiveSubscription()) {
            throw CouldNotStartSubscriptionException::alreadySubscribed();
        }

        $subscription = app(SubscriptionServiceContract::class)->startTrial($this, $planId, $trialDays);

        // Dispatch trial started event
        event(new TrialStarted($subscription, $this, $trialDays));

        return $subscription;
    }

    /**
     * Get subscription history for this subscriber
     */
    public function subscriptionHistory(): Collection
    {
        return $this->subscriptions()
            ->with(['plan', 'planPricing'])
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc') // Secondary sort by ID for consistency when timestamps are identical
            ->get();
    }

    /**
     * Check if subscriber can upgrade/downgrade to a different plan
     */
    public function canChangePlan(): bool
    {
        $subscription = $this->activeSubscription();

        if (!$subscription || !$subscription->status->isActive()) {
            return false;
        }

        // Check if plan changes are allowed during trial (configurable)
        if ($subscription->status === \AhmedEssam\SubSphere\Enums\SubscriptionStatus::TRIAL) {
            return config('sub-sphere.plan_changes.allow_plan_change_during_trial', true);
        }

        return true;
    }

    /**
     * Change subscription plan (with optional usage reset)
     */
    public function changePlan(int $newPlanId, ?bool $resetUsage = null): bool
    {
        $subscription = $this->activeSubscription();

        if (!$subscription || !$this->canChangePlan()) {
            return false;
        }

        try {
            // Determine if usage should be reset (use config fallback if not specified)
            $shouldResetUsage = $resetUsage ?? config('sub-sphere.plan_changes.reset_usage_on_plan_change', true);

            // Get the current plan pricing to use for change
            $currentPricing = $subscription->planPricing;
            if (!$currentPricing) {
                return false;
            }

            // Execute plan change using the dedicated action
            $changeAction = app(ChangeSubscriptionPlanAction::class);
            $newSubscription = $changeAction->execute($this, $newPlanId, $currentPricing->id);

            // Dispatch subscription changed event if successful
            if ($newSubscription) {
                event(new SubscriptionChanged(
                    $this,
                    $newSubscription,
                    $subscription->plan,
                    $newSubscription->plan,
                    [
                        'reset_usage' => $shouldResetUsage,
                        'changed_at'  => now(),
                    ]
                ));
                return true;
            }

            return false;
        } catch (\Exception $e) {
            // Log the error but don't throw - return false to indicate failure
            Log::error('Plan change failed', [
                'subscriber_id'   => $this->getKey(),
                'subscriber_type' => get_class($this),
                'current_plan_id' => $subscription->plan_id,
                'new_plan_id'     => $newPlanId,
                'error'           => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Cancel current active subscription
     */
    public function cancelSubscription(): bool
    {
        $subscription = $this->activeSubscription();

        if (!$subscription) {
            return false;
        }

        try {
            $cancelAction = new CancelSubscriptionAction($subscription);
            $cancelAction->execute();
            return true;
        } catch (\Exception $e) {
            Log::error('Subscription cancellation failed', [
                'subscriber_id'   => $this->getKey(),
                'subscriber_type' => get_class($this),
                'subscription_id' => $subscription->id,
                'error'           => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Resume current canceled subscription
     */
    public function resumeSubscription(): bool
    {
        // Find the most recent canceled subscription that can be resumed
        $subscription = $this->subscriptions()
            ->where('status', SubscriptionStatus::CANCELED)
            ->where('ends_at', '>', now()) // Still within the paid period
            ->orderBy('updated_at', 'desc')
            ->first();

        if (!$subscription) {
            return false;
        }

        try {
            $resumeAction = new ResumeSubscriptionAction($subscription);
            $resumeAction->execute();
            return true;
        } catch (\Exception $e) {
            Log::error('Subscription resumption failed', [
                'subscriber_id'   => $this->getKey(),
                'subscriber_type' => get_class($this),
                'subscription_id' => $subscription->id,
                'error'           => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Renew current active subscription
     */
    public function renewSubscription(): bool
    {
        $subscription = $this->activeSubscription();

        if (!$subscription) {
            return false;
        }

        // Check if subscription can be renewed
        if (!$subscription->canRenew()) {
            return false;
        }

        try {
            $renewAction = new RenewSubscriptionAction($subscription, false); // Manual renewal
            $renewAction->execute();
            return true;
        } catch (\Exception $e) {
            Log::error('Subscription renewal failed', [
                'subscriber_id'   => $this->getKey(),
                'subscriber_type' => get_class($this),
                'subscription_id' => $subscription->id,
                'error'           => $e->getMessage(),
            ]);

            return false;
        }
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
     * Create a new subscription for this subscriber
     */
    public function newSubscription(Plan $plan, PlanPricing $pricing, array $attributes = []): Subscription
    {
        $defaults = [
            'subscriber_type' => get_class($this),
            'subscriber_id'   => $this->id,
            'plan_id'         => $plan->id,
            'plan_pricing_id' => $pricing->id,
            'status'          => SubscriptionStatus::ACTIVE,
            'is_auto_renewal' => false,
            'starts_at'       => now(),
            'ends_at'         => $pricing->duration_in_days > 0 ? now()->addDays($pricing->duration_in_days) : null,
        ];

        return $this->subscriptions()->create(array_merge($defaults, $attributes));
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
