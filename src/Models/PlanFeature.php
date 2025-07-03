<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Models;

use AhmedEssam\SubSphere\Casts\TranslatableFlexibleValueCast;
use AhmedEssam\SubSphere\Enums\FeatureResetPeriod;
use AhmedEssam\SubSphere\Traits\HandlesFlexibleValues;
use AhmedEssam\SubSphere\Traits\HasTranslatableHelpers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

/**
 * Plan Feature Model
 * 
 * Represents features available in a subscription plan.
 * Contains only Eloquent relationships - business logic is in traits.
 */
class PlanFeature extends Model
{
    use HasTranslations, HasTranslatableHelpers, HandlesFlexibleValues;

    protected $table = 'plan_features';

    protected $fillable = [
        'plan_id',
        'key',
        'name',
        'description',
        'value',
        'reset_period',
    ];

    protected $translatable = [
        'name',
        'description',
    ];

    protected $casts = [
        'reset_period' => FeatureResetPeriod::class,
        'value' => TranslatableFlexibleValueCast::class,
    ];

    /**
     * Get the plan this feature belongs to
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
