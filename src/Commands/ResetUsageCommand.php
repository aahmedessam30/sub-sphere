<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Commands;

use AhmedEssam\SubSphere\Models\SubscriptionUsage;
use AhmedEssam\SubSphere\Models\PlanFeature;
use AhmedEssam\SubSphere\Enums\FeatureResetPeriod;
use AhmedEssam\SubSphere\Enums\SubscriptionStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

use function now;

/**
 * ResetUsageCommand
 * 
 * Automatically resets feature usage counters based on their reset periods.
 * Runs as a scheduled task to ensure accurate usage tracking.
 */
class ResetUsageCommand extends BaseSubscriptionCommand
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'subscriptions:reset-usage
                            {--period= : Reset period to process (daily, monthly, yearly, or all)}
                            {--dry-run : Show what would be reset without actually resetting}
                            {--limit= : Maximum number of usage records to process}';

    /**
     * The console command description.
     */
    protected $description = 'Reset feature usage counters based on their reset periods';

    /**
     * Execute the command
     */
    protected function executeCommand(): int
    {
        $dryRun = $this->option('dry-run');
        $period = $this->option('period');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        $this->logProgress($dryRun ? 'Starting dry run for usage reset...' : 'Starting usage reset...');

        // Validate period option
        if ($period && !in_array($period, ['daily', 'monthly', 'yearly', 'all'])) {
            $this->error('Invalid period. Must be one of: daily, monthly, yearly, all');
            return self::FAILURE;
        }

        // Get reset periods to process
        $resetPeriods = $this->getResetPeriodsToProcess($period);

        if (empty($resetPeriods)) {
            $this->logProgress('No valid reset periods to process.');
            return self::SUCCESS;
        }

        $totalResetCount = 0;
        $totalFailedCount = 0;
        $errors = [];

        foreach ($resetPeriods as $resetPeriod) {
            $this->logProgress("Processing {$resetPeriod->value} resets...");

            $result = $this->processResetPeriod($resetPeriod, $dryRun, $limit);

            $totalResetCount += $result['reset'];
            $totalFailedCount += $result['failed'];
            $errors = array_merge($errors, $result['errors']);
        }

        // Log overall results
        $results = [
            'reset_periods' => count($resetPeriods),
            'total_reset' => $totalResetCount,
            'total_failed' => $totalFailedCount,
            'mode' => $dryRun ? 'dry-run' : 'live',
        ];

        $this->logResults($results);

        if (!empty($errors)) {
            $this->error('Errors encountered:');
            foreach ($errors as $error) {
                $this->error("  - {$error}");
            }
        }

        return $totalFailedCount > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Get reset periods to process based on option
     *
     * @return array<FeatureResetPeriod>
     */
    private function getResetPeriodsToProcess(?string $period): array
    {
        if ($period === 'all' || $period === null) {
            return FeatureResetPeriod::automaticResetPeriods();
        }

        return match ($period) {
            'daily' => [FeatureResetPeriod::DAILY],
            'monthly' => [FeatureResetPeriod::MONTHLY],
            'yearly' => [FeatureResetPeriod::YEARLY],
            default => []
        };
    }

    /**
     * Process resets for a specific reset period
     */
    private function processResetPeriod(
        FeatureResetPeriod $resetPeriod,
        bool $dryRun,
        ?int $limit
    ): array {
        // Find usage records that need reset for this period
        $usageRecordsToReset = $this->getUsageRecordsToReset($resetPeriod, $limit);

        if ($usageRecordsToReset->isEmpty()) {
            $this->logProgress("No {$resetPeriod->value} usage records found that need reset.");
            return ['reset' => 0, 'failed' => 0, 'errors' => []];
        }

        $this->logProgress("Found {$usageRecordsToReset->count()} {$resetPeriod->value} usage record(s) to reset");

        $resetCount = 0;
        $failedCount = 0;
        $errors = [];

        foreach ($usageRecordsToReset as $usage) {
            try {
                if ($dryRun) {
                    $this->line("Would reset usage for subscription {$usage->subscription_id}, feature '{$usage->key}' (used: {$usage->used})");
                } else {
                    $this->resetUsageRecord($usage);
                    $this->logProgress("Reset usage for subscription {$usage->subscription_id}, feature '{$usage->key}'");
                }

                $resetCount++;
            } catch (\Exception $e) {
                $failedCount++;
                $error = "Failed to reset usage {$usage->id}: {$e->getMessage()}";
                $errors[] = $error;
                $this->error($error);
            }
        }

        return [
            'reset' => $resetCount,
            'failed' => $failedCount,
            'errors' => $errors
        ];
    }

    /**
     * Get usage records that need reset for a specific period
     */
    private function getUsageRecordsToReset(
        FeatureResetPeriod $resetPeriod,
        ?int $limit = null
    ): Collection {
        $query = SubscriptionUsage::query()
            ->select('subscription_usages.*')
            ->join('subscriptions', 'subscription_usages.subscription_id', '=', 'subscriptions.id')
            ->join('plan_features', function ($join) {
                $join->on('subscriptions.plan_id', '=', 'plan_features.plan_id')
                    ->on('subscription_usages.key', '=', 'plan_features.key');
            })
            ->where('plan_features.reset_period', $resetPeriod->value)
            ->whereIn('subscriptions.status', SubscriptionStatus::activeStatuses())
            ->where('subscription_usages.used', '>', 0) // Only reset if there's actual usage
            ->where(function ($query) use ($resetPeriod) {
                // Check if reset is due based on last_used_at or updated_at
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
     * Reset a usage record
     */
    private function resetUsageRecord(SubscriptionUsage $usage): void
    {
        $oldUsed = $usage->used;

        // Reset the usage counter
        $usage->update([
            'used' => 0,
            'last_used_at' => null,
        ]);

        // Dispatch FeatureUsageReset event for tracking/auditing
        event(new \AhmedEssam\SubSphere\Events\FeatureUsageReset(
            $usage,
            $oldUsed,
            0
        ));

        // Log reset with old/new values for audit trail
        Log::info('Feature usage reset', [
            'subscription_id' => $usage->subscription_id,
            'feature_key' => $usage->feature_key,
            'old_used' => $oldUsed,
            'new_used' => 0,
            'reset_at' => now()->toISOString(),
        ]);

        $this->info("  ↳ Reset from {$oldUsed} to 0");
    }

    /**
     * Get command-specific metrics for logging
     */
    protected function getCommandMetrics(): array
    {
        return [
            'total_usage_records' => SubscriptionUsage::count(),
            'active_subscriptions' => \AhmedEssam\SubSphere\Models\Subscription::whereIn('status', SubscriptionStatus::activeStatuses())->count(),
            'features_with_reset_periods' => PlanFeature::whereIn('reset_period', array_map(fn($p) => $p->value, FeatureResetPeriod::automaticResetPeriods()))->count(),
        ];
    }
}