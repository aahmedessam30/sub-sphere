<?php

namespace AhmedEssam\SubSphere\Enums;

/**
 * Subscription Status Enum
 * 
 * Defines all possible subscription states in the system lifecycle.
 * Used for status transitions and business logic validation.
 */
enum SubscriptionStatus: string
{
    case PENDING  = 'pending';
    case TRIAL    = 'trial';
    case ACTIVE   = 'active';
    case INACTIVE = 'inactive';
    case CANCELED = 'canceled';
    case EXPIRED  = 'expired';

    /**
     * Get all active statuses (subscription is usable)
     *
     * @return array<SubscriptionStatus>
     */
    public static function activeStatuses(): array
    {
        return [
            self::TRIAL,
            self::ACTIVE,
        ];
    }

    /**
     * Get all inactive statuses (subscription is not usable)
     *
     * @return array<SubscriptionStatus>
     */
    public static function inactiveStatuses(): array
    {
        return [
            self::PENDING,
            self::INACTIVE,
            self::CANCELED,
            self::EXPIRED,
        ];
    }

    /**
     * Check if this status represents an active subscription
     */
    public function isActive(): bool
    {
        return in_array($this, self::activeStatuses(), true);
    }

    /**
     * Check if this status represents an inactive subscription
     */
    public function isInactive(): bool
    {
        return in_array($this, self::inactiveStatuses(), true);
    }

    /**
     * Get human-readable label for the status
     */
    public function label(): string
    {
        $translationKey = "sub-sphere::subscription.statuses.$this->value";
        $translation    = __($translationKey);

        // Fallback to hardcoded values if translation is not found
        if ($translation === $translationKey) {
            return match ($this) {
                self::PENDING  => 'Pending',
                self::TRIAL    => 'Trial',
                self::ACTIVE   => 'Active',
                self::INACTIVE => 'Inactive',
                self::CANCELED => 'Canceled',
                self::EXPIRED  => 'Expired',
            };
        }

        return $translation;
    }

    /**
     * Get valid transitions from current status
     *
     * @return array<SubscriptionStatus>
     */
    public function validTransitions(): array
    {
        return match ($this) {
            self::PENDING  => [self::TRIAL, self::ACTIVE, self::CANCELED],
            self::TRIAL    => [self::ACTIVE, self::CANCELED, self::EXPIRED],
            self::ACTIVE   => [self::INACTIVE, self::CANCELED, self::EXPIRED],
            self::INACTIVE => [self::ACTIVE, self::CANCELED, self::EXPIRED],
            self::CANCELED => [self::ACTIVE],                                    
            self::EXPIRED  => [self::ACTIVE],                                    
        };
    }

    /**
     * Check if transition to target status is valid
     */
    public function canTransitionTo(SubscriptionStatus $target): bool
    {
        return in_array($target, $this->validTransitions(), true);
    }
}