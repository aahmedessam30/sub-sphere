<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Events;

use AhmedEssam\SubSphere\Models\Subscription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * FeatureUsed Event
 * 
 * Fired when a feature is consumed/used by a subscriber.
 */
class FeatureUsed implements ShouldQueue
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly Model $subscriber,
        public readonly string $featureKey,
        public readonly int $amount,
        public readonly int $remainingUsage
    ) {}
}
