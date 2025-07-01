<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Traits\Subscription;

use AhmedEssam\SubSphere\Models\SubscriptionUsage;
use AhmedEssam\SubSphere\Models\PlanFeature;
use AhmedEssam\SubSphere\Enums\FeatureResetPeriod;
use Illuminate\Database\Eloquent\Collection;

/**
 * FeatureUsageTracking Trait
 * 
 * Provides feature usage tracking capabilities for Subscription model.
 * Handles consumption, limits, resets, and usage queries.
 */
trait FeatureUsageTracking
{
    /**
     * Get usage record for a specific feature
     */
    public function getUsage(string $featureKey): ?SubscriptionUsage
    {
        return $this->usages()
            ->where('key', $featureKey)
            ->first();
    }

    /**
     * Get or create usage record for a feature
     */
    public function getOrCreateUsage(string $featureKey): SubscriptionUsage
    {
        return $this->usages()
            ->firstOrCreate(
                ['key' => $featureKey],
                ['used' => 0, 'last_used_at' => null]
            );
    }

    /**
     * Get all usage records for this subscription
     */
    public function getAllUsages(): Collection
    {
        return $this->usages()->get();
    }

    /**
     * Get feature definition from plan
     */
    public function getPlanFeature(string $featureKey): ?PlanFeature
    {
        return $this->plan->features()
            ->where('key', $featureKey)
            ->first();
    }

    /**
     * Check if subscription has access to a feature
     */
    public function hasFeature(string $featureKey): bool
    {
        return $this->getPlanFeature($featureKey) !== null;
    }

    /**
     * Get feature value (limit) from plan
     */
    public function getFeatureValue(string $featureKey): mixed
    {
        $feature = $this->getPlanFeature($featureKey);

        if ($feature === null) {
            return null;
        }

        // Parse feature value based on type
        return $this->parseFeatureValue($feature->value);
    }

    /**
     * Get current usage for a feature
     */
    public function getFeatureUsage(string $featureKey): int
    {
        $usage = $this->getUsage($featureKey);
        return $usage ? $usage->used : 0;
    }

    /**
     * Get remaining usage for a feature
     */
    public function getRemainingUsage(string $featureKey): ?int
    {
        $limit = $this->getFeatureValue($featureKey);

        // Unlimited feature
        if ($limit === null) {
            return null;
        }

        // Non-numeric limits (e.g., boolean features)
        if (!is_numeric($limit)) {
            return null;
        }

        $used = $this->getFeatureUsage($featureKey);
        return max(0, (int) $limit - $used);
    }

    /**
     * Check if feature usage is at or over limit
     */
    public function isFeatureExhausted(string $featureKey): bool
    {
        $remaining = $this->getRemainingUsage($featureKey);

        // Unlimited or non-numeric features are never exhausted
        if ($remaining === null) {
            return false;
        }

        return $remaining <= 0;
    }

    /**
     * Check if feature can be consumed
     */
    public function canConsumeFeature(string $featureKey, int $amount = 1): bool
    {
        if (!$this->hasFeature($featureKey)) {
            return false;
        }

        if (!$this->isActive()) {
            return false;
        }

        $remaining = $this->getRemainingUsage($featureKey);

        // Unlimited feature
        if ($remaining === null) {
            return true;
        }

        return $remaining >= $amount;
    }

    /**
     * Consume feature usage
     */
    public function consumeFeature(string $featureKey, int $amount = 1): bool
    {
        // First check if usage should be reset due to expired period
        $this->resetFeatureUsageIfExpired($featureKey);

        if (!$this->canConsumeFeature($featureKey, $amount)) {
            return false;
        }

        $usage = $this->getOrCreateUsage($featureKey);

        $usage->increment('used', $amount);
        $usage->update(['last_used_at' => now()]);

        return true;
    }

    /**
     * Reset feature usage to zero
     */
    public function resetFeatureUsage(string $featureKey): bool
    {
        $usage = $this->getUsage($featureKey);

        if ($usage === null) {
            return false;
        }

        $usage->update([
            'used' => 0,
            'last_used_at' => null,
        ]);

        return true;
    }

    /**
     * Reset all feature usages for this subscription
     */
    public function resetAllUsages(): void
    {
        $this->usages()->update([
            'used' => 0,
            'last_used_at' => null,
        ]);
    }

    /**
     * Check if feature usage should be reset based on reset period
     */
    public function shouldResetFeatureUsage(string $featureKey): bool
    {
        $feature = $this->getPlanFeature($featureKey);
        if (!$feature) {
            return false;
        }

        $usage = $this->getUsage($featureKey);
        if (!$usage || !$usage->last_used_at) {
            return false;
        }

        return $this->hasResetPeriodExpired($usage->last_used_at, $feature->reset_period);
    }

    /**
     * Check if reset period has expired since last usage
     */
    protected function hasResetPeriodExpired(\DateTime $lastUsedAt, FeatureResetPeriod $resetPeriod): bool
    {
        $now = now();
        $lastUsed = \Carbon\Carbon::parse($lastUsedAt);

        return match ($resetPeriod) {
            FeatureResetPeriod::DAILY => $lastUsed->isBefore($now->startOfDay()),
            FeatureResetPeriod::MONTHLY => $lastUsed->isBefore($now->startOfMonth()),
            FeatureResetPeriod::YEARLY => $lastUsed->isBefore($now->startOfYear()),
            FeatureResetPeriod::NEVER => false,
            default => false,
        };
    }

    /**
     * Reset feature usage if period has expired
     */
    public function resetFeatureUsageIfExpired(string $featureKey): bool
    {
        if ($this->shouldResetFeatureUsage($featureKey)) {
            return $this->resetFeatureUsage($featureKey);
        }

        return false;
    }

    /**
     * Get features that need reset based on their reset periods
     */
    public function getFeaturesNeedingReset(): Collection
    {
        return $this->plan->features()
            ->whereIn('reset_period', ['daily', 'monthly', 'yearly'])
            ->get()
            ->filter(function ($feature) {
                return $this->shouldResetFeatureUsage($feature->key);
            });
    }

    /**
     * Perform automatic resets for features that need it
     */
    public function performScheduledResets(): int
    {
        $resetCount = 0;

        foreach ($this->getFeaturesNeedingReset() as $feature) {
            if ($this->resetFeatureUsage($feature->key)) {
                $resetCount++;
            }
        }

        return $resetCount;
    }

    /**
     * Parse feature value from string to appropriate type
     */
    protected function parseFeatureValue(string $value): mixed
    {
        // Handle null/unlimited
        if (strtolower($value) === 'unlimited' || strtolower($value) === 'null') {
            return null;
        }

        // Handle boolean values
        if (strtolower($value) === 'true') {
            return true;
        }
        if (strtolower($value) === 'false') {
            return false;
        }

        // Handle JSON arrays/objects
        if (str_starts_with($value, '[') || str_starts_with($value, '{')) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        // Handle numeric values
        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        // Return as string
        return $value;
    }

    /**
     * Get usage summary for all features
     */
    public function getUsageSummary(bool $includePercentage = false, bool $groupByCategory = false): array
    {
        $summary = [];

        foreach ($this->plan->features as $feature) {
            $limit = $this->getFeatureValue($feature->key);
            $used = $this->getFeatureUsage($feature->key);
            $remaining = $this->getRemainingUsage($feature->key);

            $featureData = [
                'limit' => $limit,
                'used' => $used,
                'remaining' => $remaining,
                'exhausted' => $this->isFeatureExhausted($feature->key),
                'reset_period' => $feature->reset_period->value,
            ];

            // Include percentage used for numeric limits (useful for UI)
            if ($includePercentage && is_numeric($limit) && $limit > 0) {
                $featureData['percentage_used'] = round(($used / $limit) * 100, 2);
            }

            // Group by category if feature has category and grouping is requested
            if ($groupByCategory && isset($feature->category)) {
                $category = $feature->category;
                $summary[$category][$feature->key] = $featureData;
            } else {
                $summary[$feature->key] = $featureData;
            }
        }

        return $summary;
    }
}
