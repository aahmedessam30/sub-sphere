<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Tests\Unit;

use AhmedEssam\SubSphere\Enums\SubscriptionStatus;
use AhmedEssam\SubSphere\Models\Plan;
use AhmedEssam\SubSphere\Models\PlanPricing;
use AhmedEssam\SubSphere\Models\Subscription;
use AhmedEssam\SubSphere\Services\SubscriptionValidator;
use AhmedEssam\SubSphere\Tests\Models\User;
use AhmedEssam\SubSphere\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

/**
 * Test SubscriptionValidator functionality
 * Tests business rules validation for subscription operations
 */
class SubscriptionValidatorTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionValidator $validator;
    private User $user;
    private Plan $plan;
    private PlanPricing $pricing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new SubscriptionValidator();

        $this->user = $this->createTestUser();

        $this->plan = $this->createTestPlan([
            'slug' => 'test-plan',
            'name' => ['en' => 'Test Plan'],
            'description' => ['en' => 'A test subscription plan'],
        ]);

        $this->pricing = PlanPricing::create([
            'plan_id' => $this->plan->id,
            'label' => 'Monthly',
            'duration_in_days' => 30,
            'price' => 99.99,
        ]);

        // Create test feature for validation tests
        $this->createTestPlanFeature($this->plan, [
            'key' => 'api_calls',
            'name' => ['en' => 'API Calls'],
            'value' => '1000',
            'type' => 'limit',
        ]);

        // Set up test configuration
        Config::set('sub-sphere.trial.allow_multiple_trials_per_plan', false);
    }

    /** @test */
    public function it_validates_trial_duration_within_limits(): void
    {
        // Valid durations
        $this->validator->validateTrialDuration(7);
        $this->validator->validateTrialDuration(14);
        $this->validator->validateTrialDuration(30);

        $this->assertTrue(true); // If we reach here, validations passed
    }

    /** @test */
    public function it_throws_exception_for_trial_duration_too_short(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Trial duration must be at least 3 days');

        $this->validator->validateTrialDuration(2);
    }

    /** @test */
    public function it_throws_exception_for_trial_duration_too_long(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Trial duration cannot exceed 30 days');

        $this->validator->validateTrialDuration(31);
    }

    /** @test */
    public function it_validates_user_trial_eligibility_when_no_previous_trial(): void
    {
        $this->validator->validateUserTrialEligibility($this->user, $this->plan);
        $this->assertTrue(true); // If we reach here, validation passed
    }

    /** @test */
    public function it_throws_exception_when_user_already_used_trial(): void
    {
        // Create a previous trial subscription
        Subscription::create([
            'subscriber_type' => get_class($this->user),
            'subscriber_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::EXPIRED,
            'starts_at' => now()->subDays(60),
            'ends_at' => now()->subDays(30),
            'trial_ends_at' => now()->subDays(50),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('User has already used a trial for this plan');

        $this->validator->validateUserTrialEligibility($this->user, $this->plan);
    }

    /** @test */
    public function it_allows_multiple_trials_when_configured(): void
    {
        Config::set('sub-sphere.trial.allow_multiple_trials_per_plan', true);

        // Create a previous trial subscription
        Subscription::create([
            'subscriber_type' => get_class($this->user),
            'subscriber_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::EXPIRED,
            'starts_at' => now()->subDays(60),
            'ends_at' => now()->subDays(30),
            'trial_ends_at' => now()->subDays(50),
        ]);

        $this->validator->validateUserTrialEligibility($this->user, $this->plan);
        $this->assertTrue(true); // If we reach here, validation passed
    }

    /** @test */
    public function it_validates_plan_availability(): void
    {
        $this->validator->validatePlanAvailability($this->plan);
        $this->assertTrue(true); // If we reach here, validation passed
    }

    /** @test */
    public function it_throws_exception_for_inactive_plan(): void
    {
        $this->plan->update(['is_active' => false]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Plan is not active');

        $this->validator->validatePlanAvailability($this->plan);
    }

    /** @test */
    public function it_validates_pricing_availability(): void
    {
        $this->validator->validatePricingAvailability($this->pricing);
        $this->assertTrue(true); // If we reach here, validation passed
    }

    /** @test */
    public function it_validates_subscription_creation(): void
    {
        $this->validator->validateSubscriptionCreation($this->user, $this->plan, $this->pricing);
        $this->assertTrue(true); // If we reach here, validation passed
    }

    /** @test */
    public function it_throws_exception_when_pricing_does_not_belong_to_plan(): void
    {
        $otherPlan = $this->createTestPlan([
            'slug' => 'other-plan',
            'name' => ['en' => 'Other Plan'],
            'description' => ['en' => 'Another plan'],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Pricing does not belong to the specified plan');

        $this->validator->validateSubscriptionCreation($this->user, $otherPlan, $this->pricing);
    }

    /** @test */
    public function it_validates_renewal_eligibility_for_active_subscription(): void
    {
        $subscription = Subscription::create([
            'subscriber_type' => get_class($this->user),
            'subscriber_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        $this->validator->validateRenewalEligibility($subscription);
        $this->assertTrue(true); // If we reach here, validation passed
    }

    /** @test */
    public function it_throws_exception_for_renewal_of_canceled_subscription(): void
    {
        $subscription = Subscription::create([
            'subscriber_type' => get_class($this->user),
            'subscriber_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::CANCELED,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be renewed');

        $this->validator->validateRenewalEligibility($subscription);
    }

    /** @test */
    public function it_validates_cancellation_eligibility(): void
    {
        $subscription = Subscription::create([
            'subscriber_type' => get_class($this->user),
            'subscriber_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        $this->validator->validateCancellationEligibility($subscription);
        $this->assertTrue(true); // If we reach here, validation passed
    }

    /** @test */
    public function it_validates_resumption_eligibility(): void
    {
        $subscription = Subscription::create([
            'subscriber_type' => get_class($this->user),
            'subscriber_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::CANCELED,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        $this->validator->validateResumptionEligibility($subscription);
        $this->assertTrue(true); // If we reach here, validation passed
    }

    /** @test */
    public function it_validates_feature_consumption(): void
    {
        $subscription = Subscription::create([
            'subscriber_type' => get_class($this->user),
            'subscriber_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        $this->validator->validateFeatureConsumption($subscription, 'api_calls', 5);
        $this->assertTrue(true); // If we reach here, validation passed
    }

    /** @test */
    public function it_throws_exception_for_negative_feature_consumption(): void
    {
        $subscription = Subscription::create([
            'subscriber_type' => get_class($this->user),
            'subscriber_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Feature consumption amount must be positive');

        $this->validator->validateFeatureConsumption($subscription, 'api_calls', 0);
    }

    /** @test */
    public function it_validates_subscription_state(): void
    {
        $subscription = Subscription::create([
            'subscriber_type' => get_class($this->user),
            'subscriber_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        $this->validator->validateSubscriptionState($subscription);
        $this->assertTrue(true); // If we reach here, validation passed
    }

    /** @test */
    public function it_throws_exception_for_invalid_subscription_dates(): void
    {
        $subscription = Subscription::create([
            'subscriber_type' => get_class($this->user),
            'subscriber_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now()->addDays(10), // Starts after it ends
            'ends_at' => now(),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Subscription start date cannot be after end date');

        $this->validator->validateSubscriptionState($subscription);
    }

    /** @test */
    public function it_validates_feature_key_format(): void
    {
        $this->validator->validateFeatureKey('api_calls');
        $this->validator->validateFeatureKey('storage-limit');
        $this->validator->validateFeatureKey('feature123');

        $this->assertTrue(true); // If we reach here, validation passed
    }

    /** @test */
    public function it_throws_exception_for_empty_feature_key(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Feature key cannot be empty');

        $this->validator->validateFeatureKey('');
    }

    /** @test */
    public function it_throws_exception_for_invalid_feature_key_format(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Feature key must contain only alphanumeric characters');

        $this->validator->validateFeatureKey('feature with spaces');
    }

    /** @test */
    public function it_validates_status_transitions(): void
    {
        $this->validator->validateStatusTransition(SubscriptionStatus::ACTIVE, SubscriptionStatus::CANCELED);
        $this->validator->validateStatusTransition(SubscriptionStatus::CANCELED, SubscriptionStatus::ACTIVE);

        $this->assertTrue(true); // If we reach here, validation passed
    }

    /** @test */
    public function it_throws_exception_for_invalid_status_transition(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot transition subscription from');

        $this->validator->validateStatusTransition(SubscriptionStatus::EXPIRED, SubscriptionStatus::TRIAL);
    }

    /** @test */
    public function it_can_get_validation_summary(): void
    {
        $subscription = Subscription::create([
            'subscriber_type' => get_class($this->user),
            'subscriber_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        $summary = $this->validator->getValidationSummary($subscription);

        $this->assertArrayHasKey('is_valid', $summary);
        $this->assertArrayHasKey('errors', $summary);
        $this->assertArrayHasKey('warnings', $summary);
        $this->assertArrayHasKey('operations_allowed', $summary);

        $this->assertTrue($summary['is_valid']);
        $this->assertEmpty($summary['errors']);
        $this->assertContains('cancel', $summary['operations_allowed']);
    }

    /** @test */
    public function it_includes_warnings_for_expiring_subscriptions(): void
    {
        $subscription = Subscription::create([
            'subscriber_type' => get_class($this->user),
            'subscriber_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now()->subDays(27),
            'ends_at' => now()->addDays(2), // Expires in 2 days
        ]);

        $summary = $this->validator->getValidationSummary($subscription);

        $this->assertContains('Subscription expires within 3 days', $summary['warnings']);
    }

    /** @test */
    public function it_can_validate_complete_subscription_creation(): void
    {
        $this->validator->validateCompleteSubscriptionCreation(
            $this->user,
            $this->plan,
            $this->pricing,
            7
        );

        $this->assertTrue(true); // If we reach here, validation passed
    }

    /** @test */
    public function it_throws_exception_when_user_has_active_subscription(): void
    {
        // Create active subscription
        Subscription::create([
            'subscriber_type' => get_class($this->user),
            'subscriber_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Subscriber already has an active subscription');

        $this->validator->validateCompleteSubscriptionCreation(
            $this->user,
            $this->plan,
            $this->pricing
        );
    }

    /** @test */
    public function it_can_validate_subscription_for_specific_operations(): void
    {
        $subscription = Subscription::create([
            'subscriber_type' => get_class($this->user),
            'subscriber_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        $this->validator->validateSubscriptionForOperation($subscription, 'cancel');
        $this->validator->validateSubscriptionForOperation($subscription, 'renew');

        $this->assertTrue(true); // If we reach here, validation passed
    }

    /** @test */
    public function it_throws_exception_for_unknown_operation(): void
    {
        $subscription = Subscription::create([
            'subscriber_type' => get_class($this->user),
            'subscriber_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown operation: unknown');

        $this->validator->validateSubscriptionForOperation($subscription, 'unknown');
    }
}
