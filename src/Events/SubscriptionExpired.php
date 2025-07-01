<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Events;

use AhmedEssam\SubSphere\Models\Subscription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * SubscriptionExpired Event
 * 
 * Fired when a subscription expires and loses access.
 */
class SubscriptionExpired implements ShouldQueue
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly Model $subscriber,
        public readonly bool $wasInGracePeriod = false
    ) {}
}
