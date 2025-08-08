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
use Illuminate\Support\Facades\Log;

/**
 * HasSubscriptionActions Trait
 * 
 * Provides action methods for performing subscription operations.
 * These methods modify subscription state and trigger business logic.
 */
trait HasSubscriptionActions
{
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
}
