<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Contracts;

use AhmedEssam\SubSphere\Models\Subscription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;

/**
 * Subscription Repository Contract
 * 
 * Defines the interface for subscription data access operations.
 * Abstracts query logic for better testability and reusability.
 */
interface SubscriptionRepositoryContract
{
    /**
     * Get active subscription for a specific subscriber
     */
    public function getActiveSubscriptionFor(Model $subscriber): ?Subscription;

    /**
     * Get all expired subscriptions that need processing
     */
    public function getExpiredSubscriptions(?int $limit = null): Collection;

    /**
     * Get subscriptions expiring within specified days
     */
    public function getSubscriptionsExpiringWithin(int $days, ?int $limit = null): Collection;

    /**
     * Get subscriptions eligible for auto-renewal
     */
    public function getSubscriptionsEligibleForRenewal(?int $limit = null): Collection;

    /**
     * Get subscriptions by status
     */
    public function getSubscriptionsByStatus(array $statuses, ?int $limit = null): Collection;

    /**
     * Search subscriptions with filters
     */
    public function search(array $filters = []): Collection;

    /**
     * Get subscription with all necessary relationships loaded
     */
    public function findWithRelations(int $subscriptionId): ?Subscription;

    /**
     * Get subscriptions requiring usage reset for a specific period
     */
    public function getSubscriptionsRequiringUsageReset(string $period, ?int $limit = null): Collection;

    /**
     * Count active subscriptions for a subscriber
     */
    public function countActiveSubscriptionsFor(Model $subscriber): int;

    /**
     * Check if subscriber has used trial for a specific plan
     */
    public function hasUsedTrialForPlan(Model $subscriber, int $planId): bool;
}
