<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Traits\Subscription;

use AhmedEssam\SubSphere\Enums\SubscriptionStatus;
use Carbon\Carbon;

/**
 * SubscriptionBehaviors Trait
 * 
 * Provides business logic methods for Subscription model.
 * Handles status transitions, validation, and state checking.
 */
trait SubscriptionBehaviors
{
    /**
     * Check if subscription is currently active (usable)
     */
    public function isActive(): bool
    {
        return $this->status->isActive() && $this->hasValidPeriod();
    }

    /**
     * Check if subscription is in trial period
     */
    public function isOnTrial(): bool
    {
        return $this->status === SubscriptionStatus::TRIAL
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->isFuture();
    }

    /**
     * Check if subscription is in grace period
     */
    public function isInGracePeriod(): bool
    {
        return $this->grace_ends_at !== null
            && $this->grace_ends_at->isFuture()
            && ($this->ends_at === null || $this->ends_at->isPast());
    }

    /**
     * Check if subscription period is valid (not expired)
     */
    public function hasValidPeriod(): bool
    {
        // Lifetime subscription
        if ($this->ends_at === null) {
            return true;
        }

        // Check if we're in grace period
        if ($this->isInGracePeriod()) {
            return true;
        }

        // Check normal expiry
        return $this->ends_at->isFuture();
    }

    /**
     * Check if subscription can be renewed
     */
    public function canRenew(): bool
    {
        return in_array($this->status, [
            SubscriptionStatus::ACTIVE,
            SubscriptionStatus::EXPIRED,
            SubscriptionStatus::INACTIVE,
        ], true);
    }

    /**
     * Check if subscription can be canceled
     */
    public function canCancel(): bool
    {
        return in_array($this->status, [
            SubscriptionStatus::ACTIVE,
            SubscriptionStatus::TRIAL,
        ], true);
    }

    /**
     * Check if subscription can be resumed
     */
    public function canResume(): bool
    {
        return $this->status === SubscriptionStatus::CANCELED
            && $this->hasValidPeriod();
    }

    /**
     * Get days remaining in subscription
     */
    public function daysRemaining(): ?int
    {
        if ($this->ends_at === null) {
            return null; // Lifetime
        }

        $endDate = $this->isInGracePeriod()
            ? $this->grace_ends_at
            : $this->ends_at;

        return $endDate ? (int) max(0, now()->diffInDays($endDate, false)) : null;
    }

    /**
     * Check if this is a lifetime subscription
     */
    public function isLifetime(): bool
    {
        return $this->ends_at === null;
    }

    /**
     * Get trial days remaining
     */
    public function trialDaysRemaining(): ?int
    {
        if (!$this->isOnTrial() || $this->trial_ends_at === null) {
            return null;
        }

        return max(0, now()->diffInDays($this->trial_ends_at, false));
    }

    /**
     * Mark subscription as canceled
     */
    public function markAsCanceled(): static
    {
        if (!$this->canCancel()) {
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.behavior_cannot_cancel', [
                'status' => $this->status->value
            ]));
        }

        $this->status = SubscriptionStatus::CANCELED;

        // Start grace period when canceling
        $this->startGracePeriod();

        return $this;
    }

    /**
     * Mark subscription as expired
     */
    public function markAsExpired(): static
    {
        $this->status = SubscriptionStatus::EXPIRED;
        return $this;
    }

    /**
     * Mark subscription as active
     */
    public function markAsActive(): static
    {
        if (!$this->status->canTransitionTo(SubscriptionStatus::ACTIVE)) {
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.behavior_cannot_activate', [
                'status' => $this->status->value
            ]));
        }

        $this->status = SubscriptionStatus::ACTIVE;
        return $this;
    }

    /**
     * Activate the subscription
     */
    public function activate(): bool
    {
        if ($this->status === SubscriptionStatus::ACTIVE) {
            return true; // Already active
        }

        $this->status = SubscriptionStatus::ACTIVE;
        $this->starts_at = $this->starts_at ?? now();

        return $this->save();
    }

    /**
     * Cancel the subscription
     */
    public function cancel(): bool
    {
        if (!$this->canCancel()) {
            return false;
        }

        $this->status = SubscriptionStatus::CANCELED;

        return $this->save();
    }

    /**
     * Expire the subscription
     */
    public function expire(): bool
    {
        $this->status = SubscriptionStatus::EXPIRED;

        return $this->save();
    }

    /**
     * Resume a canceled subscription
     */
    public function resume(): bool
    {
        if (!$this->canResume()) {
            return false;
        }

        $this->status = SubscriptionStatus::ACTIVE;
        $this->grace_ends_at = null;

        return $this->save();
    }

    /**
     * Renew the subscription
     */
    public function renew(?Carbon $newEndDate = null): bool
    {
        if (!$this->canRenew()) {
            return false;
        }

        $this->status = SubscriptionStatus::ACTIVE;

        if ($newEndDate) {
            $this->ends_at = $newEndDate;
        } elseif ($this->planPricing) {
            // Extend by the plan's duration
            $this->ends_at = ($this->ends_at && $this->ends_at->isFuture())
                ? $this->ends_at->addDays($this->planPricing->duration_in_days)
                : now()->addDays($this->planPricing->duration_in_days);
        }

        return $this->save();
    }

    /**
     * Check if subscription is canceled
     */
    public function isCanceled(): bool
    {
        return $this->status === SubscriptionStatus::CANCELED;
    }

    /**
     * Check if subscription is expired
     */
    public function isExpired(): bool
    {
        return $this->status === SubscriptionStatus::EXPIRED
            || ($this->ends_at && $this->ends_at->isPast() && !$this->isInGracePeriod());
    }

    /**
     * Check if subscription is on trial (alias for consistency)
     */
    public function onTrial(): bool
    {
        return $this->isOnTrial();
    }

    /**
     * Get remaining days in subscription
     */
    public function getRemainingDays(): ?int
    {
        return $this->daysRemaining();
    }

    /**
     * Check if subscription is ending soon (within X days)
     */
    public function isEndingSoon(int $days = 7): bool
    {
        if (!$this->ends_at) {
            return false; // Lifetime subscription
        }

        $daysUntilEnd = now()->diffInDays($this->ends_at, false);
        return $daysUntilEnd >= 0 && $daysUntilEnd <= $days;
    }

    /**
     * Start grace period for subscription
     */
    public function startGracePeriod(): static
    {
        if ($this->ends_at === null) {
            return $this; // No grace period for lifetime subscriptions
        }

        $graceDays = config('sub-sphere.grace_period_days', 3);
        $this->grace_ends_at = $this->ends_at->copy()->addDays($graceDays);

        return $this;
    }

    /**
     * Check if subscription should auto-renew
     */
    public function shouldAutoRenew(): bool
    {
        return $this->is_auto_renewal
            && $this->status === SubscriptionStatus::ACTIVE
            && $this->ends_at !== null
            && $this->ends_at->isPast();
    }

    /**
     * Get subscription renewal date
     */
    public function getNextRenewalDate(): ?Carbon
    {
        if (!$this->is_auto_renewal || $this->ends_at === null) {
            return null;
        }

        return $this->ends_at->copy();
    }

    /**
     * Validate subscription state consistency
     * 
     * Validates that subscription dates and states are logically consistent.
     * Additional business rule validations can be added here as needed.
     */
    public function validateState(): bool
    {
        // Basic validations
        if ($this->starts_at !== null && $this->ends_at !== null) {
            if ($this->starts_at->isAfter($this->ends_at)) {
                return false;
            }
        }

        // Trial validation
        if ($this->status === SubscriptionStatus::TRIAL) {
            if ($this->trial_ends_at === null || $this->trial_ends_at->isPast()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if subscription is inactive
     */
    public function isInactive(): bool
    {
        return $this->status === SubscriptionStatus::INACTIVE;
    }

    /**
     * Extend subscription by a number of days
     */
    public function extend(int $days): bool
    {
        if ($this->ends_at) {
            $this->ends_at = $this->ends_at->addDays($days);
        } else {
            // If no end date (lifetime), set it from now
            $this->ends_at = now()->addDays($days);
        }

        return true;
    }
}
