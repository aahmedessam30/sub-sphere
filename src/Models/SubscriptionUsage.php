<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Subscription Usage Model
 * 
 * Tracks feature usage for a subscription.
 * Contains only Eloquent relationships - business logic is in traits.
 */
class SubscriptionUsage extends Model
{
    protected $table = 'subscription_usages';

    protected $fillable = [
        'subscription_id',
        'key',
        'used',
        'last_used_at',
    ];

    protected $casts = [
        'used' => 'integer',
        'last_used_at' => 'datetime',
    ];

    /**
     * Get the subscription this usage belongs to
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
