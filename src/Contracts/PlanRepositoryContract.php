<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Contracts;

use AhmedEssam\SubSphere\Models\Plan;
use AhmedEssam\SubSphere\Models\PlanPricing;
use Illuminate\Database\Eloquent\Collection;

/**
 * Plan Repository Contract
 * 
 * Defines the interface for plan data access operations.
 * Handles plan queries, pricing lookups, and feature management.
 */
interface PlanRepositoryContract
{
    /**
     * Get all active plans
     */
    public function getActivePlans(): Collection;

    /**
     * Get plan by slug with relationships
     */
    public function getPlanBySlug(string $slug): ?Plan;

    /**
     * Get available pricing options for a plan
     */
    public function getAvailablePricingOptions(Plan $plan, ?string $currency = null): Collection;

    /**
     * Get plan with all features and pricing options
     */
    public function getPlanWithDetails(int $planId): ?Plan;

    /**
     * Search plans with filters
     */
    public function search(array $filters = []): Collection;

    /**
     * Get popular/featured plans
     */
    public function getFeaturedPlans(?int $limit = null): Collection;

    /**
     * Get plans available for upgrade from current plan
     */
    public function getUpgradeOptionsFor(Plan $currentPlan): Collection;

    /**
     * Get plans available for downgrade from current plan
     */
    public function getDowngradeOptionsFor(Plan $currentPlan): Collection;

    /**
     * Check if plan exists and is active
     */
    public function isPlanActive(int $planId): bool;

    /**
     * Get pricing by ID with currency support
     */
    public function getPricingById(int $pricingId, ?string $currency = null): ?PlanPricing;

    /**
     * Get all currencies available for a pricing option
     */
    public function getAvailableCurrencies(int $pricingId): array;
}
