<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Actions;

use AhmedEssam\SubSphere\Models\Subscription;
use AhmedEssam\SubSphere\Events\SubscriptionStarted;

/**
 * ResumeSubscriptionAction
 * 
 * Handles resumption of canceled subscriptions.
 * Validates that subscription can be resumed and restores active status.
 */
class ResumeSubscriptionAction extends BaseAction
{
    public function __construct(
        private readonly Subscription $subscription
    ) {}

    /**
     * Validate that subscription can be resumed
     */
    protected function validate(): void
    {
        if (!$this->subscription->canResume()) {
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.cannot_resume_status', [
                'status' => $this->subscription->status->value
            ]));
        }

        // Ensure subscription still has valid period
        if (!$this->subscription->hasValidPeriod()) {
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.cannot_resume_expired'));
        }

        // Validate that the plan is still active
        $validator = app(\AhmedEssam\SubSphere\Services\SubscriptionValidator::class);
        $validator->validatePlanAvailability($this->subscription->plan);        // Check if subscriber now has another active subscription
        // Business rule: Only allow one active subscription per subscriber
        $activeSubscriptions = $this->subscription->subscriber->subscriptions()
            ->active()
            ->where('id', '!=', $this->subscription->id)
            ->count();

        if ($activeSubscriptions > 0) {
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.cannot_resume_has_active'));
        }
    }

    /**
     * Execute subscription resumption
     */
    public function execute(): Subscription
    {
        // Resume the subscription (sets status to ACTIVE)
        $this->subscription->resume();
        $this->subscription->save();

        // Dispatch subscription started event (resumption is like starting again)
        event(new SubscriptionStarted(
            $this->subscription,
            $this->subscription->subscriber,
            false // Not a trial
        ));

        return $this->subscription;
    }

    /**
     * Static factory method for convenience
     */
    public static function for(Subscription $subscription): self
    {
        return new self($subscription);
    }

    /**
     * Check how many days are left in the subscription
     */
    public function getDaysRemaining(): ?int
    {
        return $this->subscription->daysRemaining();
    }

    /**
     * Check if subscription will be in grace period after resumption
     */
    public function willBeInGracePeriod(): bool
    {
        return $this->subscription->isInGracePeriod();
    }
}
