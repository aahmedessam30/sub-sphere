<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Services;

use AhmedEssam\SubSphere\Contracts\SubscriptionServiceContract;
use AhmedEssam\SubSphere\Models\Subscription;
use AhmedEssam\SubSphere\Models\Plan;
use AhmedEssam\SubSphere\Models\PlanPricing;
use AhmedEssam\SubSphere\Enums\SubscriptionStatus;
use AhmedEssam\SubSphere\Events\SubscriptionStarted;
use AhmedEssam\SubSphere\Events\SubscriptionRenewed;
use AhmedEssam\SubSphere\Events\SubscriptionCanceled;
use AhmedEssam\SubSphere\Events\SubscriptionExpired;
use AhmedEssam\SubSphere\Events\TrialStarted;
use AhmedEssam\SubSphere\Events\FeatureUsed;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SubscriptionService
 * 
 * Orchestrates all subscription business logic using model traits.
 * Handles lifecycle management, feature access, and event dispatching.
 */
class SubscriptionService implements SubscriptionServiceContract
{
    /**
     * Subscribe a user to a plan with specific pricing
     */
    public function subscribe(
        Model $subscriber,
        int $planId,
        int $pricingId,
        ?int $trialDays = null
    ): Subscription {
        return DB::transaction(function () use ($subscriber, $planId, $pricingId, $trialDays) {
            // Validate inputs
            $plan = $this->validatePlan($planId);
            $pricing = $this->validatePricing($pricingId, $planId);
            $this->ensureNoActiveSubscription($subscriber);

            // Create subscription
            $subscription = $this->createSubscription($subscriber, $plan, $pricing, $trialDays);

            // Dispatch appropriate event
            if ($trialDays && $trialDays > 0) {
                event(new TrialStarted($subscription, $subscriber, $trialDays));
            } else {
                event(new SubscriptionStarted($subscription, $subscriber, false));
            }

            return $subscription;
        });
    }

    /**
     * Start a trial subscription for a user
     */
    public function startTrial(Model $subscriber, int $planId, int $durationInDays): Subscription
    {
        return DB::transaction(function () use ($subscriber, $planId, $durationInDays) {
            // Validate inputs
            $plan = $this->validatePlan($planId);
            $this->ensureNoActiveSubscription($subscriber);

            // Get appropriate pricing for trial (configurable behavior)
            $pricing = $this->getTrialPricing($plan);
            if (!$pricing) {
                throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.plan_no_pricing', ['plan_id' => $planId]));
            }

            // Create trial subscription
            $subscription = $this->createTrialSubscription($subscriber, $plan, $pricing, $durationInDays);

            // Dispatch event
            event(new TrialStarted($subscription, $subscriber, $durationInDays));

            return $subscription;
        });
    }

    /**
     * Renew an existing subscription
     */
    public function renew(Subscription $subscription): Subscription
    {
        return DB::transaction(function () use ($subscription) {
            if (!$subscription->canRenew()) {
                throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.behavior_cannot_renew', [
                    'status' => $subscription->status->value
                ]));
            }

            // Extend subscription by its pricing duration
            $durationDays = $subscription->planPricing->duration_in_days;

            // Extend from current end date (typical subscription behavior)
            $subscription->extend($durationDays);
            $subscription->markAsActive();
            $subscription->save();

            // Dispatch event
            event(new SubscriptionRenewed(
                $subscription,
                $subscription->subscriber,
                false // Manual renewal
            ));

            return $subscription;
        });
    }

    /**
     * Cancel a subscription (keeps access until period ends)
     */
    public function cancel(Subscription $subscription): Subscription
    {
        return DB::transaction(function () use ($subscription) {
            if (!$subscription->canCancel()) {
                throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.behavior_cannot_cancel', [
                    'status' => $subscription->status->value
                ]));
            }

            $subscription->markAsCanceled();
            $subscription->save();

            // Dispatch event
            event(new SubscriptionCanceled(
                $subscription,
                $subscription->subscriber,
                false
            ));

            return $subscription;
        });
    }

    /**
     * Resume a canceled subscription
     */
    public function resume(Subscription $subscription): Subscription
    {
        return DB::transaction(function () use ($subscription) {
            if (!$subscription->canResume()) {
                throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.behavior_cannot_resume', [
                    'status' => $subscription->status->value
                ]));
            }

            $subscription->resume();
            $subscription->save();

            // Dispatch subscription started event
            event(new SubscriptionStarted(
                $subscription,
                $subscription->subscriber,
                false
            ));

            return $subscription;
        });
    }

    /**
     * Immediately expire a subscription
     */
    public function expire(Subscription $subscription): Subscription
    {
        return DB::transaction(function () use ($subscription) {
            $wasInGracePeriod = $subscription->isInGracePeriod();

            $subscription->markAsExpired();
            $subscription->save();

            // Dispatch event
            event(new SubscriptionExpired(
                $subscription,
                $subscription->subscriber,
                $wasInGracePeriod
            ));

            return $subscription;
        });
    }

    /**
     * Check if user has access to a specific feature
     */
    public function hasFeature(Model $subscriber, string $key): bool
    {
        $subscription = $this->getActiveSubscription($subscriber);

        if (!$subscription) {
            return false;
        }

        return $subscription->hasFeature($key);
    }

    /**
     * Get the value of a feature for a user
     */
    public function getFeatureValue(Model $subscriber, string $key): mixed
    {
        $subscription = $this->getActiveSubscription($subscriber);

        if (!$subscription) {
            return null;
        }

        return $subscription->getFeatureValue($key);
    }

    /**
     * Consume usage for a feature
     */
    public function consumeFeature(Model $subscriber, string $key, int $amount = 1): bool
    {
        // Validate input parameters
        if (empty($key)) {
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.feature_key_empty'));
        }

        if ($amount <= 0) {
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.feature_consumption_positive'));
        }

        return DB::transaction(function () use ($subscriber, $key, $amount) {
            $subscription = $this->getActiveSubscription($subscriber);

            if (!$subscription) {
                return false; // Return false for no active subscription (business logic)
            }

            // Attempt to consume feature
            $success = $subscription->consumeFeature($key, $amount);

            if ($success) {
                // Get remaining usage for event
                $remaining = $subscription->getRemainingUsage($key);

                // Dispatch event
                event(new FeatureUsed(
                    $subscription,
                    $subscriber,
                    $key,
                    $amount,
                    $remaining ?? -1 // -1 indicates unlimited
                ));
            }

            return $success;
        });
    }

    /**
     * Reset feature usage for a user
     */
    public function resetFeature(Model $subscriber, string $key): bool
    {
        $subscription = $this->getActiveSubscription($subscriber);

        if (!$subscription) {
            return false;
        }

        return $subscription->resetFeatureUsage($key);
    }

    /**
     * Get the active subscription for a subscriber
     */
    public function getActiveSubscription(Model $subscriber): ?Subscription
    {
        return $subscriber->activeSubscription();
    }

    /**
     * Get all subscriptions for a subscriber
     */
    public function getSubscriptions(Model $subscriber): Collection
    {
        return $subscriber->subscriptions()->with(['plan', 'planPricing'])->get();
    }

    /**
     * Check if a subscriber has any subscription (active or inactive)
     */
    public function hasAnySubscription(Model $subscriber): bool
    {
        return $subscriber->subscriptions()->exists();
    }

    /**
     * Get subscription by ID for a specific subscriber
     */
    public function getSubscriptionById(Model $subscriber, int $subscriptionId): ?Subscription
    {
        return $subscriber->subscriptions()->find($subscriptionId);
    }

    /**
     * Auto-renew eligible subscriptions
     * 
     * Used by scheduled commands to process automatic renewals.
     */
    public function autoRenewEligibleSubscriptions(): int
    {
        $renewedCount = 0;

        // Find subscriptions that should auto-renew
        $subscriptions = Subscription::where('is_auto_renewal', true)
            ->where('status', SubscriptionStatus::ACTIVE)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->with(['subscriber', 'plan', 'planPricing'])
            ->get();

        foreach ($subscriptions as $subscription) {
            try {
                DB::transaction(function () use ($subscription) {
                    $durationDays = $subscription->planPricing->duration_in_days;
                    $subscription->extend($durationDays);
                    $subscription->save();

                    // Dispatch auto-renewal event
                    event(new SubscriptionRenewed(
                        $subscription,
                        $subscription->subscriber,
                        true // Auto renewal
                    ));
                });

                $renewedCount++;
            } catch (\Exception $e) {
                // Log renewal failures for debugging and monitoring
                Log::error('Subscription renewal failed', [
                    'subscription_id' => $subscription->id,
                    'subscriber_id' => $subscription->subscriber_id,
                    'plan_id' => $subscription->plan_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                // Continue with other subscriptions
                continue;
            }
        }

        return $renewedCount;
    }

    /**
     * Expire subscriptions that are past their grace period
     */
    public function expireOverdueSubscriptions(): int
    {
        $expiredCount = 0;

        $subscriptions = Subscription::whereIn('status', SubscriptionStatus::activeStatuses())
            ->where(function ($query) {
                $query->where(function ($q) {
                    // Subscriptions past grace period
                    $q->whereNotNull('grace_ends_at')
                        ->where('grace_ends_at', '<=', now());
                })->orWhere(function ($q) {
                    // Subscriptions with no grace period that are expired
                    $q->whereNull('grace_ends_at')
                        ->whereNotNull('ends_at')
                        ->where('ends_at', '<=', now());
                });
            })
            ->with(['subscriber'])
            ->get();

        foreach ($subscriptions as $subscription) {
            try {
                $this->expire($subscription);
                $expiredCount++;
            } catch (\Exception $e) {
                // Continue with other subscriptions
                continue;
            }
        }

        return $expiredCount;
    }

    /**
     * Get service health status
     */
    public function getHealthStatus(): array
    {
        $activeCount = Subscription::whereIn('status', SubscriptionStatus::activeStatuses())->count();
        $expiringCount = Subscription::where('status', SubscriptionStatus::ACTIVE)
            ->where('ends_at', '<=', now()->addDays(7))
            ->count();

        $overdueCount = $this->getOverdueSubscriptionsCount();

        $status = $overdueCount > 0 ? 'warning' : 'healthy';

        return [
            'status' => $status,
            'active_subscriptions' => $activeCount,
            'expiring_soon' => $expiringCount,
            'overdue_subscriptions' => $overdueCount,
            'auto_renewal_enabled' => Subscription::where('is_auto_renewal', true)->count(),
        ];
    }

    /**
     * Get count of overdue subscriptions
     */
    private function getOverdueSubscriptionsCount(): int
    {
        return Subscription::where('status', SubscriptionStatus::ACTIVE)
            ->where('ends_at', '<', now())
            ->where(function ($query) {
                $query->whereNull('grace_ends_at')
                    ->orWhere('grace_ends_at', '<', now());
            })
            ->count();
    }

    /**
     * Get subscription statistics
     */
    public function getSubscriptionStatistics(): array
    {
        return [
            'total' => Subscription::count(),
            'active' => Subscription::where('status', SubscriptionStatus::ACTIVE)->count(),
            'expired' => Subscription::where('status', SubscriptionStatus::EXPIRED)->count(),
            'trial' => Subscription::where('status', SubscriptionStatus::TRIAL)->count(),
            'canceled' => Subscription::where('status', SubscriptionStatus::CANCELED)->count(),
        ];
    }

    // Private helper methods

    /**
     * Validate plan exists and is active
     */
    private function validatePlan(int $planId): Plan
    {
        $plan = Plan::where('id', $planId)
            ->where('is_active', true)
            ->first();

        if (!$plan) {
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.plan_not_found_or_inactive', ['plan_id' => $planId]));
        }

        return $plan;
    }

    /**
     * Validate pricing exists and belongs to plan
     */
    private function validatePricing(int $pricingId, int $planId): PlanPricing
    {
        $pricing = PlanPricing::where('id', $pricingId)
            ->where('plan_id', $planId)
            ->first();

        if (!$pricing) {
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.pricing_not_found_or_invalid', [
                'pricing_id' => $pricingId,
                'plan_id' => $planId
            ]));    
        }

        return $pricing;
    }

    /**
     * Ensure subscriber has no active subscription
     */
    private function ensureNoActiveSubscription(Model $subscriber): void
    {
        if ($subscriber->hasActiveSubscription()) {
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.subscriber_already_has_active_subscription', [
                'plan_name' => $subscriber->activeSubscription()->plan->name
            ]));
        }
    }

    /**
     * Create a new subscription
     */
    private function createSubscription(
        Model $subscriber,
        Plan $plan,
        PlanPricing $pricing,
        ?int $trialDays = null
    ): Subscription {
        $now = now();
        $startsAt = $now;

        // Calculate end date based on pricing duration
        $endsAt = $pricing->duration_in_days > 0
            ? $now->copy()->addDays($pricing->duration_in_days)
            : null; // Lifetime subscription

        // Set trial end date if trial period
        $trialEndsAt = $trialDays && $trialDays > 0
            ? $now->copy()->addDays($trialDays)
            : null;

        // Set grace period
        $graceEndsAt = $endsAt
            ? $endsAt->copy()->addDays(config('sub-sphere.grace_period_days', 3))
            : null;

        // Determine initial status
        $status = $trialDays && $trialDays > 0
            ? SubscriptionStatus::TRIAL
            : SubscriptionStatus::ACTIVE;

        $subscription = $subscriber->subscriptions()->create([
            'plan_id' => $plan->id,
            'plan_pricing_id' => $pricing->id,
            'status' => $status,
            'is_auto_renewal' => config('sub-sphere.auto_renewal_default', true),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'grace_ends_at' => $graceEndsAt,
            'trial_ends_at' => $trialEndsAt,
        ]);

        return $subscription;
    }

    /**
     * Create a trial subscription
     */
    private function createTrialSubscription(
        Model $subscriber,
        Plan $plan,
        PlanPricing $pricing,
        int $trialDays
    ): Subscription {
        return $this->createSubscription($subscriber, $plan, $pricing, $trialDays);
    }

    /**
     * Validate feature consumption before processing
     */
    private function validateFeatureConsumption(Model $subscriber, string $key, int $amount): void
    {
        if (empty(trim($key))) {
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.feature_key_cannot_be_empty'));
        }

        if ($amount <= 0) {
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.consumption_amount_positive'));
        }

        $subscription = $this->getActiveSubscription($subscriber);
        if (!$subscription) {
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.no_active_subscription_for_consumption'));
        }

        if (!$subscription->hasFeature($key)) {
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.feature_not_available_in_subscription', ['key' => $key]));
        }
    }
    /**
     * Get appropriate pricing for trial subscriptions
     */
    private function getTrialPricing(Plan $plan): ?PlanPricing
    {
        // Business rule: Use the first available pricing for trials
        // This ensures consistency and simplifies the trial flow
        return $plan->pricings()->first();
    }
}