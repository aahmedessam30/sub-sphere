<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Commands;

use AhmedEssam\SubSphere\Actions\RenewSubscriptionAction;
use AhmedEssam\SubSphere\Models\Subscription;
use AhmedEssam\SubSphere\Enums\SubscriptionStatus;

/**
 * RenewSubscriptionsCommand
 * 
 * Automatically renews eligible subscriptions that have auto-renewal enabled.
 * Runs as a scheduled task to ensure seamless subscription continuity.
 */
class RenewSubscriptionsCommand extends BaseSubscriptionCommand
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'subscriptions:renew 
                            {--dry-run : Show what would be renewed without actually renewing}
                            {--limit= : Maximum number of subscriptions to process}
                            {--grace-period= : Hours before expiry to start renewing (default: 24)}';

    /**
     * The console command description.
     */
    protected $description = 'Automatically renew eligible subscriptions with auto-renewal enabled';

    /**
     * Execute the command
     */
    protected function executeCommand(): int
    {
        $dryRun = $this->option('dry-run');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $graceHours = $this->option('grace-period') ? (int) $this->option('grace-period') : 24;

        $this->logProgress($dryRun ? 'Starting dry run for subscription renewal...' : 'Starting subscription renewal...');
        $this->logProgress("Processing subscriptions expiring within {$graceHours} hours");

        // Find subscriptions that should be renewed
        $subscriptionsToRenew = $this->getSubscriptionsToRenew($graceHours, $limit);

        if ($subscriptionsToRenew->isEmpty()) {
            $this->logProgress('No subscriptions found that need renewal.');
            return self::SUCCESS;
        }

        $this->logProgress("Found {$subscriptionsToRenew->count()} subscription(s) to renew");

        $renewedCount = 0;
        $failedCount = 0;
        $errors = [];

        foreach ($subscriptionsToRenew as $subscription) {
            try {
                if ($dryRun) {
                    $this->line("Would renew subscription {$subscription->id} for subscriber {$subscription->subscriber_type}#{$subscription->subscriber_id}");
                } else {
                    // Validate plan and pricing are still active before renewal
                    if (!$this->validateSubscriptionForRenewal($subscription)) {
                        $this->error("Skipping renewal for subscription {$subscription->id} - plan or pricing is inactive");
                        $failedCount++;
                        continue;
                    }

                    RenewSubscriptionAction::forAutoRenewal($subscription)->handle();
                    $this->logProgress("Renewed subscription {$subscription->id}");
                }

                $renewedCount++;
            } catch (\Exception $e) {
                $failedCount++;
                $errors[] = "Failed to renew subscription {$subscription->id}: {$e->getMessage()}";
                $this->error("Failed to renew subscription {$subscription->id}: {$e->getMessage()}");
            }
        }

        // Log results
        $results = [
            'processed' => $subscriptionsToRenew->count(),
            'renewed' => $renewedCount,
            'failed' => $failedCount,
            'grace_hours' => $graceHours,
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
     * Get subscriptions that should be renewed
     */
    private function getSubscriptionsToRenew(int $graceHours, ?int $limit = null)
    {
        $renewalThreshold = now()->addHours($graceHours);

        $query = Subscription::where('is_auto_renewal', true)
            ->where('status', SubscriptionStatus::ACTIVE)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', $renewalThreshold)
            ->where('ends_at', '>', now()) // Not yet expired
            ->with(['subscriber', 'plan', 'pricing'])
            ->orderBy('ends_at');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Validate that subscription can still be renewed (plan/pricing active)
     */
    private function validateSubscriptionForRenewal(Subscription $subscription): bool
    {
        $canRenew = true;
        $failureReason = null;

        // Check if plan is still active
        if (!$subscription->plan->is_active || $subscription->plan->deleted_at !== null) {
            $canRenew = false;
            $failureReason = 'Plan is no longer active';
        }        // Check if pricing is still available
        if ($canRenew && $subscription->planPricing->deleted_at !== null) {
            // Business rule: Do not allow renewal with alternative pricing
            // This ensures subscription integrity and prevents confusion
            $canRenew = false;
            $failureReason = 'Original pricing is no longer available';
        }

        // Notify subscriber when renewal fails due to inactive plan/pricing
        if (!$canRenew && $failureReason) {
            // Dispatch a notification event for the failed renewal
            event(new \AhmedEssam\SubSphere\Events\SubscriptionRenewalFailed(
                $subscription,
                $failureReason
            ));
        }

        return $canRenew;
    }
}
