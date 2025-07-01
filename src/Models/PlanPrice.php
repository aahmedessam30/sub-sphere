<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Plan Price Model
 * 
 * Represents pricing in different currencies for a specific plan pricing option.
 * Enables multi-currency subscription support.
 */
class PlanPrice extends Model
{
    protected $table = 'plan_prices';

    protected $fillable = [
        'plan_pricing_id',
        'currency_code',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /**
     * Get the plan pricing this price belongs to
     */
    public function planPricing(): BelongsTo
    {
        return $this->belongsTo(PlanPricing::class, 'plan_pricing_id');
    }

    /**
     * Get formatted price with currency
     */
    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->amount, 2) . ' ' . strtoupper($this->currency_code);
    }

    /**
     * Get price in smallest currency unit (cents)
     */
    public function getAmountInCentsAttribute(): int
    {
        return (int) ($this->amount * 100);
    }

    /**
     * Scope to filter by currency
     */
    public function scopeByCurrency($query, string $currencyCode)
    {
        return $query->where('currency_code', strtoupper($currencyCode));
    }

    /**
     * Check if this is a zero-cost price
     */
    public function isFree(): bool
    {
        return $this->amount <= 0;
    }

    /**
     * Convert amount to another currency (placeholder for future currency conversion)
     */
    public function convertTo(string $targetCurrency, float $exchangeRate = 1.0): float
    {
        // Future: integrate with currency conversion service
        return $this->amount * $exchangeRate;
    }
}
