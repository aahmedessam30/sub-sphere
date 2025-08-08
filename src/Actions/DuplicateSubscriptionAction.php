<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Actions;

use AhmedEssam\SubSphere\Models\Subscription;
use AhmedEssam\SubSphere\Models\SubscriptionUsage;
use AhmedEssam\SubSphere\Enums\SubscriptionStatus;
use AhmedEssam\SubSphere\Events\SubscriptionCreated;
use AhmedEssam\SubSphere\Events\SubscriptionStarted;
use AhmedEssam\SubSphere\Events\TrialStarted;
use AhmedEssam\SubSphere\Exceptions\CouldNotStartSubscriptionException;
use AhmedEssam\SubSphere\Exceptions\SubscriptionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * DuplicateSubscriptionAction
 * 
 * Handles duplication of an existing subscription into a new subscription
 * with the same properties, features, and usage limits, but with updated dates
 * starting from now and usage counters reset to 0.
 */
class DuplicateSubscriptionAction extends BaseAction
{
    private ?Subscription $sourceSubscription = null;
    private Model $subscriber;
    private ?Carbon $startDate = null;
    private bool $withTrial = false;

    public function __construct(array $parameters = [])
    {
        $this->subscriber = $parameters['subscriber'] ?? null;
        $subscriptionId = $parameters['subscription_id'] ?? null;
        $this->startDate = isset($parameters['start_date']) ? Carbon::parse($parameters['start_date']) : null;
        $this->withTrial = $parameters['with_trial'] ?? false;

        if ($subscriptionId) {
            $this->sourceSubscription = Subscription::find($subscriptionId);
        }
    }

    /**
     * Validate that subscription can be duplicated
     */
    protected function validate(): void
    {
        // Validate subscriber is provided
        if (!$this->subscriber) {
            throw new \InvalidArgumentException('Subscriber is required');
        }

        // Validate that the source subscription exists
        if (!$this->sourceSubscription) {
            throw new SubscriptionException('Subscription not found');
        }

        // Validate subscription belongs to the subscriber
        if (!$this->sourceSubscription->subscriber->is($this->subscriber)) {
            throw new SubscriptionException('Subscription does not belong to this subscriber');
        }

        // Validate that we can duplicate the subscription status FIRST
        $allowedStatuses = [
            SubscriptionStatus::EXPIRED,
            SubscriptionStatus::CANCELED,
            SubscriptionStatus::INACTIVE,
        ];

        if (!in_array($this->sourceSubscription->status, $allowedStatuses, true)) {
            throw new SubscriptionException('Cannot duplicate an active subscription');
        }

        // Validate that the subscriber doesn't have an active subscription
        if ($this->subscriber->hasActiveSubscription()) {
            throw CouldNotStartSubscriptionException::alreadySubscribed();
        }

        // Validate that the plan is still active
        if (!$this->sourceSubscription->plan || !$this->sourceSubscription->plan->is_active) {
            throw new SubscriptionException('Cannot duplicate subscription with inactive plan');
        }
    }

    /**
     * Execute the duplication process
     */
    public function execute(array $parameters = []): Subscription
    {
        // Handle parameters passed to execute
        if (!empty($parameters)) {
            $this->subscriber = $parameters['subscriber'] ?? $this->subscriber;
            if (isset($parameters['subscription_id'])) {
                $this->sourceSubscription = Subscription::find($parameters['subscription_id']);
            }
            if (isset($parameters['start_date'])) {
                $this->startDate = Carbon::parse($parameters['start_date']);
            }
            $this->withTrial = $parameters['with_trial'] ?? $this->withTrial;
        }

        $this->validate();

        return DB::transaction(function () {
            $startDate = $this->startDate ?: now();

            // Calculate new subscription dates based on pricing duration
            $plan = $this->sourceSubscription->plan;
            $pricing = $this->sourceSubscription->planPricing;
            $durationDays = $pricing->duration_in_days ?? 30;
            $endsAt = $durationDays > 0 ? $startDate->copy()->addDays($durationDays) : null;

            // Calculate trial end date if requested
            $trialEndsAt = null;
            $trialDays = config('sub-sphere.trial_period_days', 14);
            if ($this->withTrial && $trialDays > 0) {
                $trialEndsAt = $startDate->copy()->addDays($trialDays);
            }

            // Create new subscription with same properties but updated dates
            $newSubscription = $this->subscriber->subscriptions()->create([
                'plan_id'         => $this->sourceSubscription->plan_id,
                'plan_pricing_id' => $this->sourceSubscription->plan_pricing_id,
                'status'          => SubscriptionStatus::ACTIVE,
                'starts_at'       => $startDate,
                'ends_at'         => $endsAt,
                'trial_ends_at'   => $trialEndsAt,
                'canceled_at'     => null,
                'resumed_at'      => null,
                'renewed_at'      => null,
            ]);

            // Duplicate usage records but reset usage values to 0
            $this->duplicateUsageRecords($newSubscription, $startDate);

            // Dispatch events
            event(new SubscriptionCreated(
                $this->subscriber,
                $newSubscription,
                $plan,
                [
                    'action' => 'duplicate',
                    'original_subscription_id' => $this->sourceSubscription->id,
                    'with_trial' => $this->withTrial,
                ]
            ));

            event(new SubscriptionStarted($newSubscription, $this->subscriber, $this->withTrial));

            if ($trialEndsAt) {
                event(new TrialStarted($newSubscription, $this->subscriber, $trialDays));
            }

            return $newSubscription;
        });
    }

    /**
     * Duplicate subscription usage records with reset values
     */
    private function duplicateUsageRecords(Subscription $newSubscription, Carbon $startDate): void
    {
        // Get all usage records from source subscription
        $sourceUsageRecords = $this->sourceSubscription->usages;

        foreach ($sourceUsageRecords as $sourceUsage) {
            // Calculate valid_until based on plan feature reset period
            $validUntil = $this->calculateValidUntil($sourceUsage->key, $startDate);

            // Create new usage record with same feature but reset usage
            SubscriptionUsage::create([
                'subscription_id' => $newSubscription->id,
                'key'             => $sourceUsage->key,
                'used'            => 0, // Reset usage to 0
                'valid_until'     => $validUntil,
            ]);
        }
    }

    /**
     * Calculate valid_until date for feature based on reset period
     */
    private function calculateValidUntil(string $featureKey, Carbon $startDate): ?Carbon
    {
        // Get feature reset period from the plan feature
        $planFeature = $this->sourceSubscription->plan->features()
            ->where('key', $featureKey)
            ->first();

        if (!$planFeature || !$planFeature->reset_period || $planFeature->reset_period === 'never') {
            return null;
        }

        return match (strtolower($planFeature->reset_period->value)) {
            'daily' => $startDate->copy()->addDay(),
            'weekly' => $startDate->copy()->addWeek(),
            'monthly' => $startDate->copy()->addMonth(),
            'yearly' => $startDate->copy()->addYear(),
            default => null,
        };
    }

    /**
     * Static factory method for convenience
     */
    public static function for(array $parameters): self
    {
        return new self($parameters);
    }
}
