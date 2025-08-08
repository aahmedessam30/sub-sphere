<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Traits;

use AhmedEssam\SubSphere\Contracts\SubscriptionServiceContract;

/**
 * HasSubscriptionFeatures Trait
 * 
 * Provides feature-related methods for subscription management.
 * Handles feature access, usage, and consumption.
 */
trait HasSubscriptionFeatures
{
    /**
     * Check if subscriber has access to a specific feature
     */
    public function hasFeature(string $featureKey): bool
    {
        return app(SubscriptionServiceContract::class)->hasFeature($this, $featureKey);
    }

    /**
     * Get the value of a feature for this subscriber
     */
    public function getFeatureValue(string $featureKey): mixed
    {
        return app(SubscriptionServiceContract::class)->getFeatureValue($this, $featureKey);
    }

    /**
     * Consume feature usage for this subscriber
     */
    public function consumeFeature(string $featureKey, int $amount = 1): bool
    {
        return app(SubscriptionServiceContract::class)->consumeFeature($this, $featureKey, $amount);
    }
}
