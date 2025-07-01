<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Events;

use AhmedEssam\SubSphere\Models\Subscription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * SubscriptionRenewed Event
 * 
 * Fired when a subscription is renewed (manually or automatically).
 */
class SubscriptionRenewed implements ShouldQueue
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly Model $subscriber,
        public readonly bool $wasAutoRenewal = false
    ) {}
}
