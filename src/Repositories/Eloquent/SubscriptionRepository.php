<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Repositories\Eloquent;

use AhmedEssam\SubSphere\Contracts\SubscriptionRepositoryContract;
use AhmedEssam\SubSphere\Models\Subscription;
use AhmedEssam\SubSphere\Models\SubscriptionUsage;
use AhmedEssam\SubSphere\Enums\SubscriptionStatus;
use AhmedEssam\SubSphere\Enums\FeatureResetPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;

/**
 * Eloquent Subscription Repository
 * 
 * Concrete implementation of subscription data access operations.
 * Optimized queries with proper eager loading and caching support.
 */
class SubscriptionRepository implements SubscriptionRepositoryContract
{
    /**
     * Get active subscription for a specific subscriber
     */
    public function getActiveSubscriptionFor(Model $subscriber): ?Subscription
    {
        return Subscription::where('subscriber_type', get_class($subscriber))
            ->where('subscriber_id', $subscriber->id)
            ->whereIn('status', SubscriptionStatus::activeStatuses())
            ->with(['plan', 'pricing', 'usages'])
            ->first();
    }

    /**
     * Get all expired subscriptions that need processing
     */
    public function getExpiredSubscriptions(?int $limit = null): Collection
    {
        $query = Subscription::whereIn('status', SubscriptionStatus::activeStatuses())
            ->where(function ($query) {
                $query->where(function ($q) {
                    // Subscriptions past grace period
                    $q->whereNotNull('grace_ends_at')
                        ->where('grace_ends_at', '<=', now());
                })->orWhere(function ($q) {
                    // Subscriptions with no grace period that are expired
                    $q->whereNull('grace_ends_at')
                        ->whereNotNull('ends_at')
                        ->where('ends_at', '<=', now());
                });
            })
            ->with(['subscriber', 'plan', 'pricing'])
            ->orderBy('ends_at');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Get subscriptions expiring within specified days
     */
    public function getSubscriptionsExpiringWithin(int $days, ?int $limit = null): Collection
    {
        $query = Subscription::whereIn('status', SubscriptionStatus::activeStatuses())
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now()->addDays($days))
            ->where('ends_at', '>', now())
            ->with(['subscriber', 'plan', 'pricing'])
            ->orderBy('ends_at');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Get subscriptions eligible for auto-renewal
     */
    public function getSubscriptionsEligibleForRenewal(?int $limit = null): Collection
    {
        $query = Subscription::where('auto_renewal', true)
            ->whereIn('status', [
                SubscriptionStatus::ACTIVE,
                SubscriptionStatus::EXPIRED,
            ])
            ->where(function ($query) {
                $query->where(function ($q) {
                    // Active subscriptions ending today
                    $q->where('status', SubscriptionStatus::ACTIVE)
                        ->whereDate('ends_at', '<=', now()->endOfDay());
                })->orWhere(function ($q) {
                    // Recently expired subscriptions within grace period
                    $q->where('status', SubscriptionStatus::EXPIRED)
                        ->where(function ($subQ) {
                            $subQ->whereNull('grace_ends_at')
                                ->orWhere('grace_ends_at', '>', now());
                        });
                });
            })
            ->with(['subscriber', 'plan', 'pricing'])
            ->orderBy('ends_at');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Get subscriptions by status
     */
    public function getSubscriptionsByStatus(array $statuses, ?int $limit = null): Collection
    {
        $query = Subscription::whereIn('status', $statuses)
            ->with(['subscriber', 'plan', 'pricing'])
            ->orderBy('updated_at', 'desc');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Search subscriptions with filters
     */
    public function search(array $filters = []): Collection
    {
        $query = Subscription::query()->with(['subscriber', 'plan', 'pricing']);

        // Apply filters
        if (isset($filters['status'])) {
            if (is_array($filters['status'])) {
                $query->whereIn('status', $filters['status']);
            } else {
                $query->where('status', $filters['status']);
            }
        }

        if (isset($filters['plan_id'])) {
            $query->where('plan_id', $filters['plan_id']);
        }

        if (isset($filters['subscriber_type'])) {
            $query->where('subscriber_type', $filters['subscriber_type']);
        }

        if (isset($filters['auto_renewal'])) {
            $query->where('auto_renewal', $filters['auto_renewal']);
        }

        if (isset($filters['trial'])) {
            if ($filters['trial']) {
                $query->whereNotNull('trial_ends_at');
            } else {
                $query->whereNull('trial_ends_at');
            }
        }

        if (isset($filters['expires_before'])) {
            $query->where('ends_at', '<=', $filters['expires_before']);
        }

        if (isset($filters['expires_after'])) {
            $query->where('ends_at', '>=', $filters['expires_after']);
        }

        return $query->orderBy('updated_at', 'desc')->get();
    }

    /**
     * Get subscription with all necessary relationships loaded
     */
    public function findWithRelations(int $subscriptionId): ?Subscription
    {
        return Subscription::with([
            'subscriber',
            'plan.features',
            'pricing',
            'usages'
        ])->find($subscriptionId);
    }

    /**
     * Get subscriptions requiring usage reset for a specific period
     */
    public function getSubscriptionsRequiringUsageReset(string $period, ?int $limit = null): Collection
    {
        $resetPeriod = FeatureResetPeriod::from($period);

        $query = SubscriptionUsage::query()
            ->select('subscription_usages.*')
            ->join('subscriptions', 'subscription_usages.subscription_id', '=', 'subscriptions.id')
            ->join('plan_features', function ($join) {
                $join->on('subscriptions.plan_id', '=', 'plan_features.plan_id')
                    ->on('subscription_usages.key', '=', 'plan_features.key');
            })
            ->where('plan_features.reset_period', $resetPeriod->value)
            ->whereIn('subscriptions.status', SubscriptionStatus::activeStatuses())
            ->where('subscription_usages.used', '>', 0)
            ->where(function ($query) use ($resetPeriod) {
                if ($resetPeriod === FeatureResetPeriod::DAILY) {
                    $query->where(function ($q) {
                        $q->whereDate('subscription_usages.last_used_at', '<', now()->startOfDay())
                            ->orWhere(function ($subQ) {
                                $subQ->whereNull('subscription_usages.last_used_at')
                                    ->whereDate('subscription_usages.updated_at', '<', now()->startOfDay());
                            });
                    });
                } elseif ($resetPeriod === FeatureResetPeriod::MONTHLY) {
                    $query->where(function ($q) {
                        $q->where('subscription_usages.last_used_at', '<', now()->startOfMonth())
                            ->orWhere(function ($subQ) {
                                $subQ->whereNull('subscription_usages.last_used_at')
                                    ->where('subscription_usages.updated_at', '<', now()->startOfMonth());
                            });
                    });
                } elseif ($resetPeriod === FeatureResetPeriod::YEARLY) {
                    $query->where(function ($q) {
                        $q->where('subscription_usages.last_used_at', '<', now()->startOfYear())
                            ->orWhere(function ($subQ) {
                                $subQ->whereNull('subscription_usages.last_used_at')
                                    ->where('subscription_usages.updated_at', '<', now()->startOfYear());
                            });
                    });
                }
            })
            ->with(['subscription.subscriber', 'subscription.plan'])
            ->orderBy('subscription_usages.last_used_at', 'asc');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Count active subscriptions for a subscriber
     */
    public function countActiveSubscriptionsFor(Model $subscriber): int
    {
        return Subscription::where('subscriber_type', get_class($subscriber))
            ->where('subscriber_id', $subscriber->id)
            ->whereIn('status', SubscriptionStatus::activeStatuses())
            ->count();
    }

    /**
     * Check if subscriber has used trial for a specific plan
     */
    public function hasUsedTrialForPlan(Model $subscriber, int $planId): bool
    {
        return Subscription::where('subscriber_type', get_class($subscriber))
            ->where('subscriber_id', $subscriber->id)
            ->where('plan_id', $planId)
            ->whereNotNull('trial_ends_at')
            ->exists();
    }
}
