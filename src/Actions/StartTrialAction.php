<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Actions;

use AhmedEssam\SubSphere\Support\DTOs\SubscriptionCreationDTO;
use AhmedEssam\SubSphere\Models\Subscription;
use AhmedEssam\SubSphere\Models\Plan;
use AhmedEssam\SubSphere\Models\PlanPricing;
use AhmedEssam\SubSphere\Enums\SubscriptionStatus;
use AhmedEssam\SubSphere\Events\TrialStarted;
use AhmedEssam\SubSphere\Services\SubscriptionValidator;

/**
 * StartTrialAction
 * 
 * Handles the creation of trial subscriptions.
 * Validates trial eligibility and sets up proper trial periods.
 */
class StartTrialAction extends BaseAction
{
    private ?Plan $plan = null;
    private ?PlanPricing $pricing = null;

    public function __construct(
        private readonly SubscriptionCreationDTO $data
    ) {}

    /**
     * Validate trial creation request
     */
    protected function validate(): void
    {
        // Ensure this is a trial request
        if (!$this->data->isTrial()) {
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.trial_days_required'));
        }

        // Validate no active subscription exists
        if ($this->data->subscriber->hasActiveSubscription()) {
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.subscriber_already_has_active'));
        }

        // Validate plan exists and is active
        $this->plan = Plan::where('id', $this->data->planId)
            ->where('is_active', true)
            ->first();

        if (!$this->plan) {
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.plan_not_found_or_inactive', ['plan_id' => $this->data->planId]));
        }

        // Validate pricing exists and belongs to plan
        $this->pricing = PlanPricing::where('id', $this->data->pricingId)
            ->where('plan_id', $this->data->planId)
            ->first();

        if (!$this->pricing) {
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.pricing_not_found_or_mismatch', [
                'pricing_id' => $this->data->pricingId,
                'plan_id' => $this->data->planId
            ]));
        }

        // Validate trial duration and user eligibility
        $validator = app(SubscriptionValidator::class);
        $validator->validateTrialDuration($this->data->trialDays);
        $validator->validateUserTrialEligibility($this->data->subscriber, $this->plan);
    }

    /**
     * Execute trial subscription creation
     */
    public function execute(): Subscription
    {
        $now = now();

        // Calculate trial end date
        $trialEndsAt = $now->copy()->addDays($this->data->trialDays);

        // Calculate subscription end date (after trial converts to paid)
        $endsAt = $this->pricing->duration_in_days > 0
            ? $trialEndsAt->copy()->addDays($this->pricing->duration_in_days)
            : null; // Lifetime subscription

        // Calculate grace period
        $graceEndsAt = $endsAt
            ? $endsAt->copy()->addDays(config('sub-sphere.grace_period_days', 3))
            : null;

        // Create trial subscription
        $subscription = $this->data->subscriber->subscriptions()->create([
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::TRIAL,
            'is_auto_renewal' => config('sub-sphere.auto_renewal_default', true),
            'starts_at' => $now,
            'ends_at' => $endsAt,
            'grace_ends_at' => $graceEndsAt,
            'trial_ends_at' => $trialEndsAt,
        ]);

        // Dispatch trial started event
        event(new TrialStarted(
            $subscription,
            $this->data->subscriber,
            $this->data->trialDays
        ));

        return $subscription;
    }

    /**
     * Static factory method for convenience
     */
    public static function for(SubscriptionCreationDTO $data): self
    {
        return new self($data);
    }

    /**
     * Get trial end date (if executed)
     */
    public function getTrialEndDate(): ?\DateTime
    {
        if (!$this->data->isTrial()) {
            return null;
        }

        return now()->addDays($this->data->trialDays);
    }
}
