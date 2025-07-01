<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Actions;

/**
 * Base Action Class
 * 
 * Provides common functionality for all action classes.
 * Actions are single-responsibility classes that encapsulate focused business logic.
 */
abstract class BaseAction
{
    /**
     * Execute the action
     * 
     * Each action must implement this method to define its core behavior.
     */
    abstract public function execute(): mixed;

    /**
     * Validate input parameters
     * 
     * Override this method to implement custom validation logic.
     * Throw InvalidArgumentException for validation failures.
     */
    protected function validate(): void
    {
        // Default implementation - no validation
    }

    /**
     * Handle action execution with validation
     */
    public function handle(): mixed
    {
        $this->validate();
        return $this->execute();
    }
}
