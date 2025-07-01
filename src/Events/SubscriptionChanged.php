<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Events;

use AhmedEssam\SubSphere\Models\Plan;
use AhmedEssam\SubSphere\Models\Subscription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Subscription Changed Event
 * 
 * Fired when a subscription plan is changed (upgraded or downgraded).
 * Contains information about the old and new plans and change summary.
 */
class SubscriptionChanged
{
    use Dispatchable, SerializesModels;

    public readonly Model $subscriber;
    public readonly Subscription $subscription;
    public readonly Plan $oldPlan;
    public readonly Plan $newPlan;
    public readonly array $changeSummary;

    /**
     * Create a new event instance
     */
    public function __construct(
        Model $subscriber,
        Subscription $subscription,
        Plan $oldPlan,
        Plan $newPlan,
        array $changeSummary = []
    ) {
        $this->subscriber = $subscriber;
        $this->subscription = $subscription;
        $this->oldPlan = $oldPlan;
        $this->newPlan = $newPlan;
        $this->changeSummary = $changeSummary;
    }

    /**
     * Check if this was an upgrade
     */
    public function isUpgrade(): bool
    {
        return $this->changeSummary['change_type'] === 'upgrade';
    }

    /**
     * Check if this was a downgrade
     */
    public function isDowngrade(): bool
    {
        return $this->changeSummary['change_type'] === 'downgrade';
    }

    /**
     * Check if this was a lateral change (same tier/price)
     */
    public function isLateralChange(): bool
    {
        return $this->changeSummary['change_type'] === 'lateral';
    }

    /**
     * Get the change type as a human-readable string
     */
    public function getChangeTypeLabel(): string
    {
        return match ($this->changeSummary['change_type']) {
            'upgrade' => 'Upgrade',
            'downgrade' => 'Downgrade',
            'lateral' => 'Plan Change',
            default => 'Unknown',
        };
    }

    /**
     * Get formatted change summary for logging/display
     */
    public function getFormattedSummary(): string
    {
        $summary = sprintf(
            'Subscription %s: %s (%s) → %s (%s)',
            $this->getChangeTypeLabel(),
            $this->oldPlan->name,
            $this->changeSummary['old_pricing_label'] ?? 'Unknown',
            $this->newPlan->name,
            $this->changeSummary['new_pricing_label'] ?? 'Unknown'
        );

        if (isset($this->changeSummary['proration_amount'])) {
            $summary .= sprintf(' | Proration: %s', $this->changeSummary['proration_amount']);
        }

        return $summary;
    }
}
