<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Commands;

use AhmedEssam\SubSphere\Actions\ExpireSubscriptionAction;
use AhmedEssam\SubSphere\Models\Subscription;
use AhmedEssam\SubSphere\Enums\SubscriptionStatus;

/**
 * ExpireSubscriptionsCommand
 * 
 * Automatically expires subscriptions that have passed their grace period.
 * Runs as a scheduled task to ensure timely subscription expiration.
 */
class ExpireSubscriptionsCommand extends BaseSubscriptionCommand
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'subscriptions:expire 
                            {--dry-run : Show what would be expired without actually expiring}
                            {--limit= : Maximum number of subscriptions to process}';

    /**
     * The console command description.
     */
    protected $description = 'Expire subscriptions that have passed their grace period';

    /**
     * Execute the command
     */
    protected function executeCommand(): int
    {
        $dryRun = $this->option('dry-run');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        $this->logProgress($dryRun ? 'Starting dry run for subscription expiration...' : 'Starting subscription expiration...');

        // Find subscriptions that should be expired
        $subscriptionsToExpire = $this->getSubscriptionsToExpire($limit);

        if ($subscriptionsToExpire->isEmpty()) {
            $this->logProgress('No subscriptions found that need expiration.');
            return self::SUCCESS;
        }

        $this->logProgress("Found {$subscriptionsToExpire->count()} subscription(s) to expire");

        $expiredCount = 0;
        $failedCount = 0;
        $errors = [];

        foreach ($subscriptionsToExpire as $subscription) {
            try {
                if ($dryRun) {
                    $this->line("Would expire subscription {$subscription->id} for subscriber {$subscription->subscriber_type}#{$subscription->subscriber_id}");
                } else {
                    ExpireSubscriptionAction::for($subscription)->handle();
                    $this->logProgress("Expired subscription {$subscription->id}");
                }

                $expiredCount++;
            } catch (\Exception $e) {
                $failedCount++;
                $errors[] = "Failed to expire subscription {$subscription->id}: {$e->getMessage()}";
                $this->error("Failed to expire subscription {$subscription->id}: {$e->getMessage()}");
            }
        }

        // Log results
        $results = [
            'processed' => $subscriptionsToExpire->count(),
            'expired' => $expiredCount,
            'failed' => $failedCount,
            'mode' => $dryRun ? 'dry-run' : 'live',
        ];

        $this->logResults($results);

        if (!empty($errors)) {
            $this->error('Errors encountered:');
            foreach ($errors as $error) {
                $this->error("  - {$error}");
            }
        }

        return $failedCount > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Get subscriptions that should be expired
     */
    private function getSubscriptionsToExpire(?int $limit = null)
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
            ->with(['subscriber', 'plan', 'planPricing'])
            ->orderBy('ends_at');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }
}
