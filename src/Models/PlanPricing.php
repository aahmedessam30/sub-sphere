<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Models;

use AhmedEssam\SubSphere\Traits\HasTranslatableHelpers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * Plan Pricing Model
 * 
 * Represents pricing options for a subscription plan.
 * Contains only Eloquent relationships - business logic is in traits.
 */
class PlanPricing extends Model
{
    use HasTranslations, HasTranslatableHelpers;

    protected $table = 'plan_pricings';

    protected $fillable = [
        'plan_id',
        'label',
        'duration_in_days',
        'price',
        'is_best_offer',
    ];

    protected $translatable = [
        'label',
    ];

    protected $casts = [
        'price'            => 'decimal:2',
        'is_best_offer'    => 'boolean',
        'duration_in_days' => 'integer',
    ];

    /**
     * Get the plan this pricing belongs to
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Get all subscriptions using this pricing
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get all prices for different currencies
     */
    public function prices(): HasMany
    {
        return $this->hasMany(PlanPrice::class, 'plan_pricing_id');
    }

    /**
     * Get price for a specific currency
     */
    public function getPriceForCurrency(string $currencyCode): ?PlanPrice
    {
        return $this->prices()->byCurrency($currencyCode)->first();
    }

    /**
     * Get the default price (fallback to price column if no multi-currency prices exist)
     */
    public function getDefaultPrice(): float
    {
        return (float) $this->price;
    }

    /**
     * Get price in specified currency or default currency
     */
    public function getPriceInCurrency(string $currencyCode, ?string $defaultCurrency = null): float
    {
        $price = $this->getPriceForCurrency($currencyCode);

        if ($price) {
            return (float) $price->amount;
        }

        // Fallback to default currency if specified
        if ($defaultCurrency && $defaultCurrency !== $currencyCode) {
            $defaultPrice = $this->getPriceForCurrency($defaultCurrency);
            if ($defaultPrice) {
                return (float) $defaultPrice->amount;
            }
        }

        // Final fallback to the base price column
        return $this->getDefaultPrice();
    }
}