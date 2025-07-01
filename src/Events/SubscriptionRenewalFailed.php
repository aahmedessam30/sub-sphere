<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Events;

use AhmedEssam\SubSphere\Models\Subscription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * SubscriptionRenewalFailed Event
 * 
 * Fired when a subscription renewal fails due to business rules
 * (e.g., inactive plan, unavailable pricing).
 */
class SubscriptionRenewalFailed implements ShouldQueue
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly string $reason,
        public readonly ?array $context = null
    ) {}
}
