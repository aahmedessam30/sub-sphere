<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Exceptions;

use Exception;

/**
 * Subscription Exception
 * 
 * Base exception class for subscription-related errors.
 */
class SubscriptionException extends Exception
{
    /**
     * Create a new subscription exception
     */
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create exception for invalid subscription state
     */
    public static function invalidState(string $details = ''): self
    {
        return new self(__('sub-sphere::subscription.errors.invalid_state', ['details' => $details]));
    }

    /**
     * Create exception for plan change violations
     */
    public static function planChangeNotAllowed(string $reason = ''): self
    {
        return new self(__('sub-sphere::subscription.errors.plan_change_not_allowed', ['reason' => $reason]));
    }

    /**
     * Create exception for usage limit violations
     */
    public static function usageLimitExceeded(string $feature = '', int $used = 0, int $limit = 0): self
    {
        return new self(__('sub-sphere::subscription.errors.usage_limit_exceeded', [
            'feature' => $feature,
            'used' => $used,
            'limit' => $limit
        ]));
    }

    /**
     * Create exception for subscription not found
     */
    public static function notFound(string $identifier = ''): self
    {
        return new self(__('sub-sphere::subscription.errors.subscription_not_found', ['identifier' => $identifier]));
    }

    /**
     * Create exception for expired subscriptions
     */
    public static function expired(string $identifier = ''): self
    {
        return new self(__('sub-sphere::subscription.errors.subscription_expired', ['identifier' => $identifier]));
    }
}
