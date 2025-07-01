<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Models;

use AhmedEssam\SubSphere\Traits\HasTranslatableHelpers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

/**
 * Plan Model
 * 
 * Represents a subscription plan with multiple pricing options and features.
 * Contains only Eloquent relationships - business logic is in traits.
 */
class Plan extends Model
{
    use SoftDeletes, HasTranslations, HasTranslatableHelpers;

    protected $table = 'plans';

    protected $fillable = [
        'slug',
        'name',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $translatable = [
        'name',
        'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get all pricing options for this plan
     */
    public function pricings(): HasMany
    {
        return $this->hasMany(PlanPricing::class);
    }

    /**
     * Get all features for this plan
     */
    public function features(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    /**
     * Get all subscriptions using this plan
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
