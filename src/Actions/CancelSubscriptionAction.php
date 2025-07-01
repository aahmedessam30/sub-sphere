<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Actions;

use AhmedEssam\SubSphere\Models\Subscription;
use AhmedEssam\SubSphere\Events\SubscriptionCanceled;
use AhmedEssam\SubSphere\Contracts\SubscriptionServiceContract;

/**
 * CancelSubscriptionAction
 * 
 * Handles the cancellation of a subscription.
 * Subscription retains access until the end of the billing period.
 */
class CancelSubscriptionAction extends BaseAction
{
    public function __construct(
        private readonly Subscription $subscription
    ) {}

    /**
     * Validate that subscription can be canceled
     */
    protected function validate(): void
    {
        if (!$this->subscription->canCancel()) {
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.cannot_cancel_status', [
                'status' => $this->subscription->status->value
            ]));
        }
    }

    /**
     * Execute subscription cancellation
     */
    public function execute(): Subscription
    {
        // Mark subscription as canceled
        $this->subscription->markAsCanceled();
        $this->subscription->save();

        // Dispatch cancellation event
        event(new SubscriptionCanceled(
            $this->subscription,
            $this->subscription->subscriber,
            false // Not immediately expired
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
}