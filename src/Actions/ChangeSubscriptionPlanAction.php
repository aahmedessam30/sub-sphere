<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Actions;

use AhmedEssam\SubSphere\Events\SubscriptionChanged;
use AhmedEssam\SubSphere\Exceptions\SubscriptionException;
use AhmedEssam\SubSphere\Models\Plan;
use AhmedEssam\SubSphere\Models\PlanPricing;
use AhmedEssam\SubSphere\Models\Subscription;
use AhmedEssam\SubSphere\Contracts\PlanRepositoryContract;
use AhmedEssam\SubSphere\Contracts\SubscriptionRepositoryContract;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Change Subscription Plan Action
 * 
 * Handles the complex logic of upgrading or downgrading subscription plans.
 * Includes business rules validation, proration, usage resets, and event dispatch.
 */
class ChangeSubscriptionPlanAction
{
    public function __construct(
        protected SubscriptionRepositoryContract $subscriptionRepository,
        protected PlanRepositoryContract $planRepository
    ) {}

    /**
     * Execute the plan change
     */
    public function execute(
        Model $subscriber,
        int $newPlanId,
        int $newPricingId,
        ?string $currency = null
    ): Subscription {
        return DB::transaction(function () use ($subscriber, $newPlanId, $newPricingId, $currency) {
            // Get current active subscription
            $currentSubscription = $this->subscriptionRepository->getActiveSubscriptionFor($subscriber);
            if (!$currentSubscription) {
                throw new SubscriptionException(__('sub-sphere::subscription.errors.no_active_subscription_for_change'));
            }

            // Get new plan and pricing
            $newPlan = $this->planRepository->getPlanWithDetails($newPlanId);
            if (!$newPlan || !$newPlan->is_active) {
                throw new SubscriptionException(__('sub-sphere::subscription.errors.target_plan_not_available'));
            }

            $newPricing = $this->planRepository->getPricingById($newPricingId, $currency);
            if (!$newPricing || $newPricing->plan_id !== $newPlanId) {
                throw new SubscriptionException(__('sub-sphere::subscription.errors.target_pricing_not_available'));
            }

            // Validate the plan change
            $this->validatePlanChange($currentSubscription, $newPlan, $newPricing);

            // Determine change type and calculate proration
            $changeSummary = $this->calculateChangeSummary($currentSubscription, $newPlan, $newPricing, $currency);

            // Create new subscription
            $newSubscription = $this->createNewSubscription($subscriber, $newPlan, $newPricing, $changeSummary);

            // Handle usage resets and adjustments
            $this->handleUsageAdjustments($currentSubscription, $newSubscription, $changeSummary);

            // Cancel old subscription
            $this->cancelOldSubscription($currentSubscription, $changeSummary);

            // Always log plan changes (business rule)
            $this->logPlanChange($subscriber, $currentSubscription, $newSubscription, $changeSummary);

            // Dispatch event
            SubscriptionChanged::dispatch(
                $subscriber,
                $newSubscription,
                $currentSubscription->plan,
                $newPlan,
                $changeSummary
            );

            return $newSubscription;
        });
    }

    /**
     * Validate that the plan change is allowed
     */
    protected function validatePlanChange(Subscription $currentSubscription, Plan $newPlan, PlanPricing $newPricing): void
    {
        // Check if downgrades are allowed
        if (!config('sub-sphere.plan_changes.allow_downgrades', true)) {
            $changeType = $this->determineChangeType($currentSubscription->pricing, $newPricing);
            if ($changeType === 'downgrade') {
                throw new SubscriptionException(__('sub-sphere::subscription.errors.plan_downgrades_not_allowed'));
            }
        }

        // Check usage limits for downgrades
        if (config('sub-sphere.plan_changes.prevent_downgrade_with_excess_usage', true)) {
            $this->validateUsageLimitsForDowngrade($currentSubscription, $newPlan);
        }

        // Ensure the new plan is different
        if (
            $currentSubscription->plan_id === $newPlan->id &&
            $currentSubscription->plan_pricing_id === $newPricing->id
        ) {
            throw new SubscriptionException(__('sub-sphere::subscription.errors.same_plan_change_not_allowed'));
        }
    }

    /**
     * Validate usage limits for downgrade scenarios
     */
    protected function validateUsageLimitsForDowngrade(Subscription $currentSubscription, Plan $newPlan): void
    {
        $currentUsages = $currentSubscription->usages;
        $newFeatures = $newPlan->features;

        foreach ($currentUsages as $usage) {
            $newFeature = $newFeatures->firstWhere('feature_code', $usage->feature_code);

            if ($newFeature && $newFeature->limit_type !== 'unlimited') {
                if ($usage->used_quantity > $newFeature->limit_value) {
                    throw new SubscriptionException(__('sub-sphere::subscription.errors.downgrade_usage_exceeds_limit', [
                        'feature' => $usage->feature_code,
                        'used' => $usage->used_quantity,
                        'limit' => $newFeature->limit_value
                    ]));
                }
            }
        }
    }

    /**
     * Calculate change summary including proration
     */
    protected function calculateChangeSummary(
        Subscription $currentSubscription,
        Plan $newPlan,
        PlanPricing $newPricing,
        ?string $currency
    ): array {
        $oldPricing = $currentSubscription->pricing;
        $changeType = $this->determineChangeType($oldPricing, $newPricing);

        $summary = [
            'change_type' => $changeType,
            'old_plan_id' => $currentSubscription->plan_id,
            'new_plan_id' => $newPlan->id,
            'old_pricing_id' => $oldPricing->id,
            'new_pricing_id' => $newPricing->id,
            'old_pricing_label' => $oldPricing->label,
            'new_pricing_label' => $newPricing->label,
            'currency' => $currency ?: config('sub-sphere.currency.default', 'USD'),
            'changed_at' => now(),
        ];

        // Always calculate proration (business rule)
        $summary['proration_amount'] = $this->calculateProration($currentSubscription, $newPricing, $currency);

        return $summary;
    }

    /**
     * Determine if this is an upgrade, downgrade, or lateral change
     */
    protected function determineChangeType(PlanPricing $oldPricing, PlanPricing $newPricing): string
    {
        // Simple price comparison (can be enhanced with more sophisticated logic)
        $oldPrice = $oldPricing->getDefaultPrice();
        $newPrice = $newPricing->getDefaultPrice();

        if ($newPrice > $oldPrice) {
            return 'upgrade';
        } elseif ($newPrice < $oldPrice) {
            return 'downgrade';
        } else {
            return 'lateral';
        }
    }

    /**
     * Calculate proration amount
     */
    protected function calculateProration(Subscription $currentSubscription, PlanPricing $newPricing, ?string $currency): float
    {
        $daysRemaining = $currentSubscription->ends_at->diffInDays(now());
        $totalDays = $currentSubscription->pricing->duration_in_days;

        if ($daysRemaining <= 0 || $totalDays <= 0) {
            return 0.0;
        }

        $oldDailyRate = $currentSubscription->pricing->getDefaultPrice() / $totalDays;
        $newDailyRate = $newPricing->getPriceInCurrency($currency ?: config('sub-sphere.currency.default')) / $newPricing->duration_in_days;

        $unusedCredit = $oldDailyRate * $daysRemaining;
        $newChargeForRemaining = $newDailyRate * $daysRemaining;

        return round($newChargeForRemaining - $unusedCredit, 2);
    }

    /**
     * Create new subscription with appropriate dates
     */
    protected function createNewSubscription(
        Model $subscriber,
        Plan $plan,
        PlanPricing $pricing,
        array $changeSummary
    ): Subscription {
        $startDate = now();

        // For downgrades, no grace period (immediate effect - business rule)
        // This ensures consistent behavior and prevents confusion

        return Subscription::create([
            'subscriber_type' => get_class($subscriber),
            'subscriber_id' => $subscriber->getKey(),
            'plan_id' => $plan->id,
            'plan_pricing_id' => $pricing->id,
            'starts_at' => $startDate,
            'expires_at' => $startDate->addDays($pricing->duration_in_days),
            'trial_ends_at' => null, // No trial for plan changes
            'auto_renewal' => config('sub-sphere.auto_renewal_default', true),
            'status' => 'active',
        ]);
    }

    /**
     * Handle usage adjustments based on configuration
     */
    protected function handleUsageAdjustments(
        Subscription $oldSubscription,
        Subscription $newSubscription,
        array $changeSummary
    ): void {
        // Business rules for usage reset during plan changes:
        // - Upgrades: Don't reset usage (preserve current usage)
        // - Downgrades: Reset usage to prevent over-usage on new limits
        $shouldReset = $changeSummary['change_type'] === 'downgrade';

        if ($shouldReset) {
            // Copy current usage to new subscription and optionally reset
            foreach ($oldSubscription->usages as $usage) {
                $newSubscription->usages()->create([
                    'key' => $usage->key,
                    'used' => 0, // Reset on plan change
                    'last_used_at' => now(),
                ]);
            }
        } else {
            // Copy current usage levels
            foreach ($oldSubscription->usages as $usage) {
                $newSubscription->usages()->create([
                    'key' => $usage->key,
                    'used' => $usage->used,
                    'last_used_at' => $usage->last_used_at,
                ]);
            }
        }
    }

    /**
     * Cancel the old subscription
     */
    protected function cancelOldSubscription(Subscription $subscription, array $changeSummary): void
    {
        $subscription->update([
            'status' => 'canceled',
            'is_auto_renewal' => false,
        ]);
    }

    /**
     * Log the plan change for audit trail
     */
    protected function logPlanChange(
        Model $subscriber,
        Subscription $oldSubscription,
        Subscription $newSubscription,
        array $changeSummary
    ): void {
        Log::info('Subscription plan changed', [
            'subscriber_type' => get_class($subscriber),
            'subscriber_id' => $subscriber->getKey(),
            'old_subscription_id' => $oldSubscription->id,
            'new_subscription_id' => $newSubscription->id,
            'change_summary' => $changeSummary,
        ]);
    }
}
