<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Events;

use AhmedEssam\SubSphere\Models\Plan;
use AhmedEssam\SubSphere\Models\Subscription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Subscription Created Event
 * 
 * Fired when a new subscription is successfully created.
 */
class SubscriptionCreated
{
    use Dispatchable, SerializesModels;

    public readonly Model $subscriber;
    public readonly Subscription $subscription;
    public readonly Plan $plan;
    public readonly array $details;

    /**
     * Create a new event instance
     */
    public function __construct(
        Model $subscriber,
        Subscription $subscription,
        Plan $plan,
        array $details = []
    ) {
        $this->subscriber = $subscriber;
        $this->subscription = $subscription;
        $this->plan = $plan;
        $this->details = $details;
    }

    /**
     * Check if this subscription has a trial period
     */
    public function hasTrial(): bool
    {
        return $this->subscription->trial_ends_at !== null;
    }

    /**
     * Check if this is a recurring subscription
     */
    public function isRecurring(): bool
    {
        return $this->subscription->auto_renewal === true;
    }

    /**
     * Get formatted subscription summary
     */
    public function getFormattedSummary(): string
    {
        $summary = sprintf(
            'New subscription created: %s (%s)',
            $this->plan->name,
            $this->details['pricing_label'] ?? 'Unknown'
        );

        if ($this->hasTrial()) {
            $summary .= sprintf(' | Trial until: %s', $this->subscription->trial_ends_at->format('Y-m-d'));
        }

        if (isset($this->details['currency'])) {
            $summary .= sprintf(' | Currency: %s', $this->details['currency']);
        }

        return $summary;
    }
}
