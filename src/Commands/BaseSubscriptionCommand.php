<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Base Subscription Command
 * 
 * Provides common functionality for subscription management commands.
 * Handles logging, error handling, and progress reporting.
 */
abstract class BaseSubscriptionCommand extends Command
{
    /**
     * Execute the command logic
     * 
     * Each command must implement this method to define its core behavior.
     */
    abstract protected function executeCommand(): int;

    /**
     * Handle command execution with error handling and logging
     */
    public function handle(): int
    {
        $startTime = microtime(true);
        $this->info("Starting {$this->getName()}...");

        try {
            $result = $this->executeCommand();

            $duration = round(microtime(true) - $startTime, 2);
            $this->info("Command completed successfully in {$duration}s");

            return $result;
        } catch (\Exception $e) {
            $this->error("Command failed: {$e->getMessage()}");

            // Log the full exception for debugging
            Log::error("Subscription command failed", [
                'command' => $this->getName(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }

    /**
     * Log command progress with optional context
     */
    protected function logProgress(string $message, array $context = []): void
    {
        $this->info($message);

        if (config('sub-sphere.log_command_progress', false)) {
            Log::info($message, array_merge(['command' => $this->getName()], $context));
        }
    }

    /**
     * Log command results
     */
    protected function logResults(array $results): void
    {
        foreach ($results as $key => $value) {
            $this->line("<info>{$key}:</info> {$value}");
        }

        if (config('sub-sphere.log_command_results', true)) {
            Log::info("Command results", array_merge(['command' => $this->getName()], $results));
        }
    }
}
