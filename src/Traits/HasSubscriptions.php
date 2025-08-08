<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Traits;

/**
 * HasSubscriptions Trait
 * 
 * Add subscription capabilities to any model (User, Team, Organization, etc.)
 * Provides relationship and convenience methods for subscription management.
 * 
 * This trait combines multiple focused traits for better organization:
 * - HasSubscriptionQueries: Read-only methods for subscription state
 * - HasSubscriptionActions: Action methods that modify subscription state
 * - HasSubscriptionValidation: Validation methods ("can" methods)
 * - HasSubscriptionFeatures: Feature-related methods
 */
trait HasSubscriptions
{
    use HasSubscriptionQueries;
    use HasSubscriptionActions;
    use HasSubscriptionValidation;
    use HasSubscriptionFeatures;
}
