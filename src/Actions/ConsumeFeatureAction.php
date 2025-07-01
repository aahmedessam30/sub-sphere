<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Actions;

use AhmedEssam\SubSphere\Support\DTOs\FeatureConsumptionDTO;
use AhmedEssam\SubSphere\Models\Subscription;
use AhmedEssam\SubSphere\Events\FeatureUsed;

/**
 * ConsumeFeatureAction
 * 
 * Handles feature consumption with validation and usage tracking.
 * Ensures features exist, limits are respected, and usage is properly recorded.
 */
class ConsumeFeatureAction extends BaseAction
{
    private ?Subscription $subscription = null;

    public function __construct(
        private readonly FeatureConsumptionDTO $data
    ) {}

    /**
     * Validate feature consumption request
     */
    protected function validate(): void
    {
        // Get active subscription
        $this->subscription = $this->data->subscriber->activeSubscription();

        if (!$this->subscription) {
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.no_active_subscription'));
        }

        if (!$this->subscription->isActive()) {
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.subscription_not_active'));
        }

        if (!$this->subscription->hasFeature($this->data->featureKey)) {
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.feature_not_available', [
                'feature_key' => $this->data->featureKey
            ]));
        }

        if (!$this->subscription->canConsumeFeature($this->data->featureKey, $this->data->amount)) {
            $remaining = $this->subscription->getRemainingUsage($this->data->featureKey);
            throw new \InvalidArgumentException(__('sub-sphere::subscription.errors.insufficient_feature_usage', [
                'requested' => $this->data->amount,
                'available' => $remaining ?? 'unlimited'
            ]));
        }
    }

    /**
     * Execute feature consumption
     */
    public function execute(): bool
    {
        // Consume the feature
        $success = $this->subscription->consumeFeature($this->data->featureKey, $this->data->amount);

        if ($success) {
            // Get remaining usage for event
            $remaining = $this->subscription->getRemainingUsage($this->data->featureKey);

            // Dispatch feature used event
            event(new FeatureUsed(
                $this->subscription,
                $this->data->subscriber,
                $this->data->featureKey,
                $this->data->amount,
                $remaining ?? -1 // -1 indicates unlimited
            ));
        }

        return $success;
    }

    /**
     * Static factory method for convenience
     */
    public static function for(FeatureConsumptionDTO $data): self
    {
        return new self($data);
    }

    /**
     * Get remaining usage after consumption (if executed)
     */
    public function getRemainingUsage(): ?int
    {
        if (!$this->subscription) {
            throw new \LogicException(__('sub-sphere::subscription.errors.action_not_executed'));
        }

        return $this->subscription->getRemainingUsage($this->data->featureKey);
    }

    /**
     * Check if feature is unlimited
     */
    public function isUnlimited(): bool
    {
        if (!$this->subscription) {
            throw new \LogicException(__('sub-sphere::subscription.errors.action_not_executed_unlimited'));
        }

        return $this->subscription->getFeatureValue($this->data->featureKey) === null;
    }
}
