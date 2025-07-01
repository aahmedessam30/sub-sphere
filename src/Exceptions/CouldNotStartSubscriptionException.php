<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Exceptions;

/**
 * CouldNotStartSubscriptionException
 * 
 * Thrown when a subscription cannot be started due to business rules violations.
 */
class CouldNotStartSubscriptionException extends SubscriptionException
{
    /**
     * Create exception for when subscriber already has an active subscription
     */
    public static function alreadySubscribed(): self
    {
        return new self(__('sub-sphere::subscription.errors.already_subscribed'));
    }

    /**
     * Create exception for when subscriber is not eligible for a trial
     */
    public static function notEligibleForTrial(string $reason = ''): self
    {
        if ($reason) {
            return new self(__('sub-sphere::subscription.errors.not_eligible_for_trial_with_reason', ['reason' => $reason]));
        }
        return new self(__('sub-sphere::subscription.errors.not_eligible_for_trial'));
    }

    /**
     * Create exception for invalid plan
     */
    public static function invalidPlan(int $planId): self
    {
        return new self(__('sub-sphere::subscription.errors.invalid_plan', ['plan_id' => $planId]));
    }

    /**
     * Create exception for invalid pricing
     */
    public static function invalidPricing(int $pricingId): self
    {
        return new self(__('sub-sphere::subscription.errors.invalid_pricing', ['pricing_id' => $pricingId]));
    }

    /**
     * Create exception for when trial days are invalid
     */
    public static function invalidTrialDuration(int $days, int $min, int $max): self
    {
        return new self(__('sub-sphere::subscription.errors.invalid_trial_duration', [
            'days' => $days,
            'min' => $min,
            'max' => $max
        ]));
    }
}