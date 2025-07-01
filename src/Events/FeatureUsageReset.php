<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Events;

use AhmedEssam\SubSphere\Models\SubscriptionUsage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * FeatureUsageReset Event
 * 
 * Fired when feature usage is reset (typically during billing cycles).
 */
class FeatureUsageReset implements ShouldQueue
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly SubscriptionUsage $usage,
        public readonly int $oldUsed,
        public readonly int $newUsed,
        public readonly ?string $resetReason = null
    ) {}
}
