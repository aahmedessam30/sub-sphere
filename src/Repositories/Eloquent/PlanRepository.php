<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Repositories\Eloquent;

use AhmedEssam\SubSphere\Contracts\PlanRepositoryContract;
use AhmedEssam\SubSphere\Models\Plan;
use AhmedEssam\SubSphere\Models\PlanPricing;
use AhmedEssam\SubSphere\Models\PlanPrice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent Plan Repository
 * 
 * Concrete implementation of plan data access operations.
 * Handles plan queries, pricing lookups, and feature management.
 */
class PlanRepository implements PlanRepositoryContract
{
    /**
     * Get all active plans
     */
    public function getActivePlans(): Collection
    {
        return Plan::where('is_active', true)
            ->whereNull('deleted_at') // Not soft deleted
            ->with(['features', 'pricings'])
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get();
    }

    /**
     * Get plan by slug with relationships
     */
    public function getPlanBySlug(string $slug): ?Plan
    {
        return Plan::where('slug', $slug)
            ->whereNull('deleted_at')
            ->with(['features', 'pricings'])
            ->first();
    }

    /**
     * Get available pricing options for a plan
     */
    public function getAvailablePricingOptions(Plan $plan, ?string $currency = null): Collection
    {
        $query = $plan->pricings();

        // Filter by currency if multi-currency prices exist
        if ($currency) {
            $query->with(['prices' => function ($q) use ($currency) {
                $q->where('currency_code', strtoupper($currency));
            }]);
        } else {
            $query->with('prices');
        }

        return $query->orderBy('price', 'asc')->get();
    }

    /**
     * Get plan with all features and pricing options
     */
    public function getPlanWithDetails(int $planId): ?Plan
    {
        return Plan::with([
            'features' => function ($query) {
                $query->orderBy('key');
            },
            'pricings' => function ($query) {
                $query->orderBy('price', 'asc');
            }
        ])->find($planId);
    }

    /**
     * Search plans with filters
     */
    public function search(array $filters = []): Collection
    {
        $query = Plan::query()->whereNull('deleted_at');

        if (isset($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }

        if (isset($filters['slug'])) {
            $query->where('slug', $filters['slug']);
        }

        if (isset($filters['price_min']) || isset($filters['price_max'])) {
            $query->whereHas('pricings', function ($q) use ($filters) {
                if (isset($filters['price_min'])) {
                    $q->where('price', '>=', $filters['price_min']);
                }
                if (isset($filters['price_max'])) {
                    $q->where('price', '<=', $filters['price_max']);
                }
            });
        }

        if (isset($filters['has_feature'])) {
            $query->whereHas('features', function ($q) use ($filters) {
                $q->where('key', $filters['has_feature']);
            });
        }

        return $query->with(['features', 'pricings'])
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get();
    }

    /**
     * Get popular/featured plans
     */
    public function getFeaturedPlans(?int $limit = null): Collection
    {
        $query = Plan::whereNull('deleted_at');

        // Check if is_featured column exists in database
        if (Schema::hasColumn('plans', 'is_featured')) {
            $query->where('is_featured', true);
        } else {
            // Fallback: consider plans with higher pricing as featured
            $query->whereHas('pricings', function ($q) {
                $q->orderBy('price', 'desc');
            });
        }

        $query->with(['features', 'pricings'])
            ->orderBy('sort_order', 'asc');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Get plans available for upgrade from current plan
     */
    public function getUpgradeOptionsFor(Plan $currentPlan): Collection
    {
        // Enhanced upgrade logic based on pricing and feature hierarchy
        $currentMinPrice = $currentPlan->pricings()->min('price') ?? 0;

        $upgradePlans = Plan::whereNull('deleted_at')
            ->where('id', '!=', $currentPlan->id)
            ->whereHas('pricings', function ($query) use ($currentMinPrice) {
                $query->where('price', '>', $currentMinPrice);
            })
            ->with(['features', 'pricings'])
            ->orderBy('sort_order', 'asc')
            ->get();

        // Additional filtering: prefer plans with more features
        return $upgradePlans->sortByDesc(function ($plan) {
            return $plan->features->count();
        })->values();
    }

    /**
     * Get plans available for downgrade from current plan
     */
    public function getDowngradeOptionsFor(Plan $currentPlan): Collection
    {
        // Enhanced downgrade logic based on pricing
        $currentMinPrice = $currentPlan->pricings()->min('price') ?? PHP_INT_MAX;

        $downgradePlans = Plan::whereNull('deleted_at')
            ->where('id', '!=', $currentPlan->id)
            ->whereHas('pricings', function ($query) use ($currentMinPrice) {
                $query->where('price', '<', $currentMinPrice);
            })
            ->with(['features', 'pricings'])
            ->orderBy('sort_order', 'desc')
            ->get();

        // Sort by price descending (highest cheaper option first)
        return $downgradePlans->sortByDesc(function ($plan) {
            return $plan->pricings->min('price');
        })->values();
    }

    /**
     * Check if plan exists and is active
     */
    public function isPlanActive(int $planId): bool
    {
        return Plan::where('id', $planId)
            ->whereNull('deleted_at')
            ->exists();
    }

    /**
     * Get pricing by ID with currency support
     */
    public function getPricingById(int $pricingId, ?string $currency = null): ?PlanPricing
    {
        $pricing = PlanPricing::with(['plan', 'prices'])->find($pricingId);

        if (!$pricing) {
            return null;
        }

        // Load currency-specific pricing when multi-currency is used
        if ($currency && $pricing->prices->count() > 0) {
            $currencyPrice = $pricing->prices->where('currency_code', strtoupper($currency))->first();
            if ($currencyPrice) {
                $pricing->setAttribute('currency_price', $currencyPrice->amount);
                $pricing->setAttribute('currency_code', $currency);
            }
        }

        return $pricing;
    }

    /**
     * Get all currencies available for a pricing option
     */
    public function getAvailableCurrencies(int $pricingId): array
    {
        return PlanPrice::where('plan_pricing_id', $pricingId)
            ->pluck('currency_code')
            ->unique()
            ->values()
            ->toArray();
    }
}
