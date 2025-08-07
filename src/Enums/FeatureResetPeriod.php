<?php

namespace AhmedEssam\SubSphere\Enums;

use Illuminate\Support\Carbon;

/**
 * Feature Reset Period Enum
 * 
 * Defines how often feature usage should be reset.
 * Used for automated usage resets via artisan commands.
 */
enum FeatureResetPeriod: string
{
    case NEVER   = 'never';
    case DAILY   = 'daily';
    case MONTHLY = 'monthly';
    case YEARLY  = 'yearly';

    /**
     * Get human-readable label for the reset period
     */
    public function label(): string
    {
        $translationKey = "sub-sphere::subscription.reset_periods.$this->value";
        $translation    = __($translationKey);

        // Fallback to hardcoded values if translation is not found
        if ($translation === $translationKey) {
            return match ($this) {
                self::NEVER   => 'Never',
                self::DAILY   => 'Daily',
                self::MONTHLY => 'Monthly',
                self::YEARLY  => 'Yearly',
            };
        }

        return $translation;
    }

    /**
     * Get the next reset date from a given date
     */
    public function getNextResetDate(?Carbon $from = null): ?Carbon
    {
        $from = $from ?? now();

        return match ($this) {
            self::NEVER   => null,
            self::DAILY   => $from->copy()->addDay()->startOfDay(),
            self::MONTHLY => $from->copy()->addMonth()->startOfMonth(),
            self::YEARLY  => $from->copy()->addYear()->startOfYear(),
        };
    }

    /**
     * Check if a reset should occur based on last reset date
     */
    public function shouldReset(?Carbon $lastReset = null): bool
    {
        if ($this === self::NEVER) {
            return false;
        }

        if ($lastReset === null) {
            return true;
        }

        $nextReset = $this->getNextResetDate($lastReset);

        return $nextReset !== null && $nextReset->isPast();
    }

    /**
     * Get all periods that support automatic resets
     *
     * @return array<FeatureResetPeriod>
     */
    public static function automaticResetPeriods(): array
    {
        return [
            self::DAILY,
            self::MONTHLY,
            self::YEARLY,
        ];
    }
}