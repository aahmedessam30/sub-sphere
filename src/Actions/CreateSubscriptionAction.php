<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Actions;

use AhmedEssam\SubSphere\Events\SubscriptionCreated;
use AhmedEssam\SubSphere\Support\DTOs\SubscriptionCreationDTO;
use AhmedEssam\SubSphere\Models\Subscription;
use AhmedEssam\SubSphere\Models\Plan;
use AhmedEssam\SubSphere\Models\PlanPricing;
use AhmedEssam\SubSphere\Enums\SubscriptionStatus;
use AhmedEssam\SubSphere\Events\SubscriptionStarted;
use AhmedEssam\SubSphere\Contracts\PlanRepositoryContract;
use AhmedEssam\SubSphere\Contracts\SubscriptionRepositoryContract;

/**
 * CreateSubscriptionAction
 * 
 * Handles the creation of paid subscriptions.
 * Validates subscriber eligibility and creates active subscription.
 */
class CreateSubscriptionAction extends BaseAction
{
    private ?Plan $plan = null;
    private ?PlanPricing $pricing = null;

    public function __construct(
        private readonly SubscriptionCreationDTO $data
    ) {}

    /**
     * Validate subscription creation request
     */
    protected function validate(): void
    {
        // Ensure this is not a trial request
        if ($this->data->isTrial()) {
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.use_trial_action'));
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
    }

    /**
     * Execute subscription creation
     */
    public function execute(): Subscription
    {
        $now = now();

        // Calculate subscription end date
        $endsAt = $this->pricing->duration_in_days > 0
            ? $now->copy()->addDays($this->pricing->duration_in_days)
            : null; // Lifetime subscription

        // Calculate grace period
        $graceEndsAt = $endsAt
            ? $endsAt->copy()->addDays(config('sub-sphere.grace_period_days', 3))
            : null;

        // Create active subscription
        $subscription = $this->data->subscriber->subscriptions()->create([
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::ACTIVE,
            'is_auto_renewal' => config('sub-sphere.auto_renewal_default', true),
            'starts_at' => $now,
            'ends_at' => $endsAt,
            'grace_ends_at' => $graceEndsAt,
            'trial_ends_at' => null,
        ]);

        // Dispatch subscription started event
        event(new SubscriptionStarted(
            $subscription,
            $this->data->subscriber,
            false // Not a trial
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
     * Check if this will be a lifetime subscription
     */
    public function isLifetime(): bool
    {
        return $this->pricing && $this->pricing->duration_in_days <= 0;
    }

    /**
     * Get subscription end date (if executed)
     */
    public function getEndDate(): ?\DateTime
    {
        if (!$this->pricing) {
            return null;
        }

        if ($this->pricing->duration_in_days <= 0) {
            return null; // Lifetime
        }

        return now()->addDays($this->pricing->duration_in_days);
    }
}
