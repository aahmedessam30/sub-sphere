<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Services;

use AhmedEssam\SubSphere\Models\Subscription;
use AhmedEssam\SubSphere\Models\Plan;
use AhmedEssam\SubSphere\Models\PlanPricing;
use AhmedEssam\SubSphere\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * SubscriptionValidator
 * 
 * Centralized validation for subscription business rules.
 * Prevents code duplication and ensures consistent validation logic.
 */
class SubscriptionValidator
{
    /**
     * Validate trial duration against business rules
     */
    public function validateTrialDuration(int $trialDays): void
    {
        $minDays = config('sub-sphere.trial.min_days', 3);
        $maxDays = config('sub-sphere.trial.max_days', 30);

        if ($trialDays < $minDays) {
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.trial_duration_min', ['min_days' => $minDays]));
        }

        if ($trialDays > $maxDays) {
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.trial_duration_max', ['max_days' => $maxDays]));
        }
    }

    /**
     * Validate that a user hasn't already used a trial for this plan
     */
    public function validateUserTrialEligibility(Model $subscriber, Plan $plan): void
    {
        $allowMultipleTrials = config('sub-sphere.trial.allow_multiple_trials_per_plan', false);

        if ($allowMultipleTrials) {
            return; // No restriction
        }

        $hasUsedTrial = Subscription::where('subscriber_type', get_class($subscriber))
            ->where('subscriber_id', $subscriber->id)
            ->where('plan_id', $plan->id)
            ->whereNotNull('trial_ends_at')
            ->exists();

        if ($hasUsedTrial) {
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.user_already_trialed'));
        }
    }

    /**
     * Validate that a plan is active and available for subscriptions
     */
    public function validatePlanAvailability(Plan $plan): void
    {
        if ($plan->trashed()) {
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.plan_not_available'));
        }

        if (!$plan->is_active) {
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.plan_not_active'));
        }
    }

    /**
     * Validate that pricing is active and available
     */
    public function validatePricingAvailability(PlanPricing $pricing): void
    {
        // PlanPricing model doesn't use SoftDeletes, so we skip the trashed() check
        // If soft deletes are added to PlanPricing model in the future, uncomment below:
        // if ($pricing->trashed()) {
        //     throw new \InvalidArgumentException("Pricing is not available (soft deleted).");
        // }

        // Check is_active field if it exists in the pricing model
        if (isset($pricing->is_active) && !$pricing->is_active) {
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.pricing_not_active'));
        }
    }

    /**
     * Validate that a subscription can be renewed
     */
    public function validateRenewalEligibility(Subscription $subscription): void
    {
        // Check subscription status
        $allowedStatuses = [
            SubscriptionStatus::ACTIVE,
            SubscriptionStatus::EXPIRED,
            SubscriptionStatus::INACTIVE,
        ];

        if (!in_array($subscription->status, $allowedStatuses)) {
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.cannot_renew_status', [
                'status' => $subscription->status->value
            ]));
        }

        // Validate plan and pricing are still available
        $this->validatePlanAvailability($subscription->plan);
        $this->validatePricingAvailability($subscription->planPricing);
    }

    /**
     * Validate that a subscription can be canceled
     */
    public function validateCancellationEligibility(Subscription $subscription): void
    {
        $cancellableStatuses = [
            SubscriptionStatus::ACTIVE,
            SubscriptionStatus::TRIAL,
        ];

        if (!in_array($subscription->status, $cancellableStatuses)) {
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.cannot_cancel_status_validation', [
                'status' => $subscription->status->value
            ]));
        }
    }

    /**
     * Validate that feature consumption is allowed for a subscription
     */
    public function validateFeatureConsumption(Subscription $subscription, string $featureKey, int $amount): void
    {
        // Check subscription status
        $activeStatuses = SubscriptionStatus::activeStatuses();

        if (!in_array($subscription->status, $activeStatuses)) {
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.subscription_not_active_for_feature', [
                'status' => $subscription->status->value
            ]));
        }

        // Validate amount is positive
        if ($amount <= 0) {
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.feature_consumption_positive'));
        }

        // Validate feature exists in the subscription plan
        $feature = $subscription->plan->features()->where('key', $featureKey)->first();
        if (!$feature) {
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.feature_not_found_in_plan', [
                'feature_key' => $featureKey
            ]));
        }
    }

    /**
     * Validate that a subscription can be resumed
     */
    public function validateResumptionEligibility(Subscription $subscription): void
    {
        $resumableStatuses = [
            SubscriptionStatus::CANCELED,
            SubscriptionStatus::EXPIRED,
        ];

        if (!in_array($subscription->status, $resumableStatuses)) {
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.cannot_resume_status_validation', [
                'status' => $subscription->status->value
            ]));
        }

        // Validate plan and pricing are still available
        $this->validatePlanAvailability($subscription->plan);
        $this->validatePricingAvailability($subscription->planPricing);
    }

    /**
     * Validate subscription creation parameters
     */
    public function validateSubscriptionCreation(Model $subscriber, Plan $plan, PlanPricing $pricing): void
    {
        // Validate plan and pricing availability
        $this->validatePlanAvailability($plan);
        $this->validatePricingAvailability($pricing);

        // Validate pricing belongs to plan
        if ($pricing->plan_id !== $plan->id) {
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.pricing_plan_mismatch'));
        }

        // Business rule: Subscriber model type validation can be implemented
        // via dependency injection or interface contracts if needed
    }

    /**
     * Check if a user has used a trial for a specific plan
     */
    public function hasUsedTrial(Model $subscriber, Plan $plan): bool
    {
        return Subscription::where('subscriber_type', get_class($subscriber))
            ->where('subscriber_id', $subscriber->id)
            ->where('plan_id', $plan->id)
            ->whereNotNull('trial_ends_at')
            ->exists();
    }

    /**
     * Validate that a subscription can be expired
     */
    public function validateExpirationEligibility(Subscription $subscription): void
    {
        $expirableStatuses = [
            SubscriptionStatus::ACTIVE,
            SubscriptionStatus::TRIAL,
        ];

        if (!in_array($subscription->status, $expirableStatuses)) {
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.cannot_expire_status', [
                'status' => $subscription->status->value
            ]));
        }

        // Prevent expiration of lifetime subscriptions
        if ($subscription->isLifetime()) {
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.lifetime_subscription_expire'));
        }
    }

    /**
     * Validate subscription state consistency
     */
    public function validateSubscriptionState(Subscription $subscription): void
    {
        // Basic date validations
        if ($subscription->starts_at && $subscription->ends_at) {
            if ($subscription->starts_at->isAfter($subscription->ends_at)) {
                throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.start_date_after_end'));
            }
        }

        // Trial validation
        if ($subscription->trial_ends_at) {
            if ($subscription->starts_at && $subscription->trial_ends_at->isBefore($subscription->starts_at)) {
                throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.trial_end_before_start'));
            }
        }

        // Grace period validation
        if ($subscription->grace_ends_at && $subscription->ends_at) {
            if ($subscription->grace_ends_at->isBefore($subscription->ends_at)) {
                throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.grace_end_invalid'));
            }
        }
    }

    /**
     * Validate feature key format
     */
    public function validateFeatureKey(string $featureKey): void
    {
        if (empty(trim($featureKey))) {
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.feature_key_empty'));
        }

        // Optional: validate format (alphanumeric with underscores/dashes)
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $featureKey)) {
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.feature_key_invalid_format'));
        }
    }

    /**
     * Validate subscription status transition
     */
    public function validateStatusTransition(SubscriptionStatus $from, SubscriptionStatus $to): void
    {
        if (!$from->canTransitionTo($to)) {
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.cannot_transition_status', [
                'from' => $from->value,
                'to' => $to->value
            ]));
        }
    }

    /**
     * Comprehensive validation for subscription creation
     */
    public function validateCompleteSubscriptionCreation(
        Model $subscriber,
        Plan $plan,
        PlanPricing $pricing,
        ?int $trialDays = null
    ): void {
        // Validate plan and pricing
        $this->validateSubscriptionCreation($subscriber, $plan, $pricing);

        // Validate trial if specified
        if ($trialDays !== null) {
            $this->validateTrialDuration($trialDays);
            $this->validateUserTrialEligibility($subscriber, $plan);
        }

        // Validate subscriber doesn't have active subscription
        $activeSubscriptions = Subscription::where('subscriber_type', get_class($subscriber))
            ->where('subscriber_id', $subscriber->id)
            ->whereIn('status', SubscriptionStatus::activeStatuses())
            ->count();

        if ($activeSubscriptions > 0) {
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.subscriber_has_active_subscription'));
        }
    }

    /**
     * Validate all aspects of a subscription before operations
     */
    public function validateSubscriptionForOperation(Subscription $subscription, string $operation): void
    {
        // Basic state validation
        $this->validateSubscriptionState($subscription);

        // Operation-specific validation
        switch (strtolower($operation)) {
            case 'cancel':
                $this->validateCancellationEligibility($subscription);
                break;
            case 'renew':
                $this->validateRenewalEligibility($subscription);
                break;
            case 'resume':
                $this->validateResumptionEligibility($subscription);
                break;
            case 'expire':
                $this->validateExpirationEligibility($subscription);
                break;
            default:
                throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.unknown_operation', ['operation' => $operation]));
        }
    }

    /**
     * Get validation summary for a subscription
     */
    public function getValidationSummary(Subscription $subscription): array
    {
        $summary = [
            'is_valid' => true,
            'errors' => [],
            'warnings' => [],
            'operations_allowed' => [],
        ];

        try {
            $this->validateSubscriptionState($subscription);
        } catch (\InvalidArgumentException $e) {
            $summary['is_valid'] = false;
            $summary['errors'][] = $e->getMessage();
        }

        // Check which operations are allowed
        $operations = ['cancel', 'renew', 'resume', 'expire'];
        foreach ($operations as $operation) {
            try {
                $this->validateSubscriptionForOperation($subscription, $operation);
                $summary['operations_allowed'][] = $operation;
            } catch (\InvalidArgumentException $e) {
                // Operation not allowed - this is normal
            }
        }

        // Add warnings for potential issues
        if ($subscription->ends_at && $subscription->ends_at->diffInDays(now()) <= 3) {
            $summary['warnings'][] = __('sub-sphere::subscription.errors.expiring_soon_warning');
        }

        if ($subscription->trial_ends_at && $subscription->trial_ends_at->diffInDays(now()) <= 1) {
            $summary['warnings'][] = __('sub-sphere::subscription.errors.trial_expiring_soon_warning');
        }

        return $summary;
    }
}
