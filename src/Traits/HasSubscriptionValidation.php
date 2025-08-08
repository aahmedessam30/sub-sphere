<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Traits;

use AhmedEssam\SubSphere\Enums\SubscriptionStatus;
use AhmedEssam\SubSphere\Models\Plan;
use AhmedEssam\SubSphere\Contracts\SubscriptionServiceContract;

/**
 * HasSubscriptionValidation Trait
 * 
 * Provides validation methods ("can" methods) for subscription operations.
 * These methods check if a subscriber is eligible to perform certain actions.
 */
trait HasSubscriptionValidation
{
    /**
     * Check if subscriber can subscribe to a plan
     */
    public function canSubscribe(?int $planId = null): bool
    {
        // Check if subscriber already has active subscription
        if ($this->hasActiveSubscription()) {
            return false;
        }

        // If specific plan is provided, validate it
        if ($planId !== null) {
            $plan = Plan::where('id', $planId)
                ->where('is_active', true)
                ->first();

            return $plan !== null;
        }

        return true;
    }

    /**
     * Check if subscriber can start a trial for a specific plan
     */
    public function canStartTrial(int $planId, ?int $trialDays = null): bool
    {
        // Check if subscriber already has active subscription
        if ($this->hasActiveSubscription()) {
            return false;
        }

        // Validate plan exists and is active
        $plan = Plan::where('id', $planId)
            ->where('is_active', true)
            ->first();

        if (!$plan) {
            return false;
        }

        // Check if multiple trials per plan are allowed
        $allowMultipleTrials = config('sub-sphere.trial.allow_multiple_trials_per_plan', false);

        if (!$allowMultipleTrials) {
            // Check if user has already used trial for this plan
            $hasUsedTrial = $this->subscriptions()
                ->where('plan_id', $planId)
                ->whereNotNull('trial_ends_at')
                ->exists();

            if ($hasUsedTrial) {
                return false;
            }
        }

        // Validate trial duration if provided
        if ($trialDays !== null) {
            $minDays = config('sub-sphere.trial.min_days', 3);
            $maxDays = config('sub-sphere.trial.max_days', 30);

            if ($trialDays < $minDays || $trialDays > $maxDays) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if subscriber can cancel their active subscription
     */
    public function canCancelSubscription(): bool
    {
        $subscription = $this->activeSubscription();

        return $subscription && $subscription->canCancel();
    }

    /**
     * Check if subscriber can resume a canceled subscription
     */
    public function canResumeSubscription(): bool
    {
        // Find the most recent canceled subscription that can be resumed
        $subscription = $this->subscriptions()
            ->where('status', SubscriptionStatus::CANCELED)
            ->where('ends_at', '>', now()) // Still within the paid period
            ->orderBy('updated_at', 'desc')
            ->first();

        return $subscription && $subscription->canResume();
    }

    /**
     * Check if subscriber can renew their subscription
     */
    public function canRenewSubscription(): bool
    {
        $subscription = $this->activeSubscription();

        return $subscription && $subscription->canRenew();
    }

    /**
     * Check if subscriber can consume a specific feature
     */
    public function canConsumeFeature(string $featureKey, int $amount = 1): bool
    {
        $subscription = $this->activeSubscription();

        if (!$subscription) {
            return false;
        }

        // Check if subscription is active
        if (!$subscription->status->isActive()) {
            return false;
        }

        // Validate amount is positive
        if ($amount <= 0) {
            return false;
        }

        // Check if feature exists in the plan and has remaining usage
        return app(SubscriptionServiceContract::class)->hasFeature($this, $featureKey);
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
        if ($subscription->status === SubscriptionStatus::TRIAL) {
            return config('sub-sphere.plan_changes.allow_plan_change_during_trial', true);
        }

        return true;
    }

    /**
     * Check if subscriber can upgrade to a specific plan
     */
    public function canUpgrade(int $newPlanId): bool
    {
        if (!$this->canChangePlan()) {
            return false;
        }

        $subscription = $this->activeSubscription();
        $currentPlan = $subscription->plan;

        // Validate new plan exists and is active
        $newPlan = Plan::where('id', $newPlanId)
            ->where('is_active', true)
            ->first();

        if (!$newPlan) {
            return false;
        }

        // Basic upgrade validation - new plan should be different
        return $currentPlan->id !== $newPlan->id;
    }

    /**
     * Check if subscriber can downgrade to a specific plan
     */
    public function canDowngrade(int $newPlanId): bool
    {
        if (!$this->canChangePlan()) {
            return false;
        }

        // Check if downgrades are allowed
        if (!config('sub-sphere.plan_changes.allow_downgrades', true)) {
            return false;
        }

        $subscription = $this->activeSubscription();
        $currentPlan = $subscription->plan;

        // Validate new plan exists and is active
        $newPlan = Plan::where('id', $newPlanId)
            ->where('is_active', true)
            ->first();

        if (!$newPlan) {
            return false;
        }

        // Basic downgrade validation - new plan should be different
        if ($currentPlan->id === $newPlan->id) {
            return false;
        }

        // Check for excess usage prevention
        if (config('sub-sphere.plan_changes.prevent_downgrade_with_excess_usage', true)) {
            // This would require additional logic to check feature usage
            // For now, we'll allow the downgrade and let the action handle detailed validation
            return true;
        }

        return true;
    }

    /**
     * Check if subscriber can access a specific feature
     */
    public function canAccessFeature(string $featureKey): bool
    {
        $subscription = $this->activeSubscription();

        if (!$subscription || !$subscription->status->isActive()) {
            return false;
        }

        return $this->hasFeature($featureKey);
    }
}
