<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Models;

use AhmedEssam\SubSphere\Enums\SubscriptionStatus;
use AhmedEssam\SubSphere\Traits\Subscription\SubscriptionBehaviors;
use AhmedEssam\SubSphere\Traits\Subscription\FeatureUsageTracking;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Subscription Model
 * 
 * Represents a user's subscription to a plan.
 * Contains only Eloquent relationships - business logic is in traits.
 */
class Subscription extends Model
{
    use SoftDeletes, SubscriptionBehaviors, FeatureUsageTracking;

    protected $table = 'subscriptions';

    protected $fillable = [
        'subscriber_type',
        'subscriber_id',
        'plan_id',
        'plan_pricing_id',
        'status',
        'is_auto_renewal',
        'starts_at',
        'ends_at',
        'grace_ends_at',
        'trial_ends_at',
    ];

    protected $casts = [
        'status' => SubscriptionStatus::class,
        'is_auto_renewal' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'grace_ends_at' => 'datetime',
        'trial_ends_at' => 'datetime',
    ];

    /**
     * Get the subscriber (User model)
     */
    public function subscriber(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the plan this subscription belongs to
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Get the pricing this subscription uses
     */
    public function planPricing(): BelongsTo
    {
        return $this->belongsTo(PlanPricing::class, 'plan_pricing_id');
    }

    /**
     * Get all usage records for this subscription
     */
    public function usages(): HasMany
    {
        return $this->hasMany(SubscriptionUsage::class);
    }
}