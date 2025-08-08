<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Tests\Unit;

use AhmedEssam\SubSphere\Enums\SubscriptionStatus;
use AhmedEssam\SubSphere\Models\Plan;
use AhmedEssam\SubSphere\Models\PlanPricing;
use AhmedEssam\SubSphere\Models\PlanFeature;
use AhmedEssam\SubSphere\Models\Subscription;
use AhmedEssam\SubSphere\Tests\Models\User;
use AhmedEssam\SubSphere\Tests\TestCase;

class HasSubscriptionsValidationMethodsTest extends TestCase
{
    private User $user;
    private Plan $plan;
    private Plan $otherPlan;
    private PlanPricing $pricing;
    private PlanPricing $otherPricing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->plan = $this->createTestPlan([
            'slug' => 'basic-plan',
            'name' => ['en' => 'Basic Plan'],
            'description' => ['en' => 'A basic subscription plan'],
        ]);

        $this->otherPlan = $this->createTestPlan([
            'slug' => 'premium-plan',
            'name' => ['en' => 'Premium Plan'],
            'description' => ['en' => 'A premium subscription plan'],
        ]);

        $this->pricing = PlanPricing::create([
            'plan_id' => $this->plan->id,
            'label' => 'Monthly',
            'duration_in_days' => 30,
            'price' => 9.99,
        ]);

        $this->otherPricing = PlanPricing::create([
            'plan_id' => $this->otherPlan->id,
            'label' => 'Monthly',
            'duration_in_days' => 30,
            'price' => 19.99,
        ]);

        // Create test features for the plans
        $this->createTestPlanFeature($this->plan, [
            'key' => 'api-calls',
            'name' => ['en' => 'API Calls'],
            'description' => ['en' => 'Number of API calls allowed'],
            'value' => '100',
        ]);

        $this->createTestPlanFeature($this->otherPlan, [
            'key' => 'api-calls',
            'name' => ['en' => 'API Calls'],
            'description' => ['en' => 'Number of API calls allowed'],
            'value' => '500',
        ]);
    }

    /** @test */
    public function it_can_subscribe_when_no_active_subscription(): void
    {
        $this->assertTrue($this->user->canSubscribe());
        $this->assertTrue($this->user->canSubscribe($this->plan->id));
    }

    /** @test */
    public function it_cannot_subscribe_when_already_has_active_subscription(): void
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

        $this->assertFalse($this->user->canSubscribe());
        $this->assertFalse($this->user->canSubscribe($this->otherPlan->id));
    }

    /** @test */
    public function it_cannot_subscribe_to_inactive_plan(): void
    {
        $inactivePlan = $this->createTestPlan([
            'slug' => 'inactive-plan',
            'name' => ['en' => 'Inactive Plan'],
            'description' => ['en' => 'An inactive plan'],
            'is_active' => false,
        ]);

        $this->assertFalse($this->user->canSubscribe($inactivePlan->id));
    }

    /** @test */
    public function it_can_start_trial_when_eligible(): void
    {
        $this->assertTrue($this->user->canStartTrial($this->plan->id));
        $this->assertTrue($this->user->canStartTrial($this->plan->id, 14));
    }

    /** @test */
    public function it_cannot_start_trial_when_already_has_active_subscription(): void
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

        $this->assertFalse($this->user->canStartTrial($this->otherPlan->id));
    }

    /** @test */
    public function it_cannot_start_trial_for_inactive_plan(): void
    {
        $inactivePlan = $this->createTestPlan([
            'slug' => 'inactive-plan',
            'name' => ['en' => 'Inactive Plan'],
            'description' => ['en' => 'An inactive plan'],
            'is_active' => false,
        ]);

        $this->assertFalse($this->user->canStartTrial($inactivePlan->id));
    }

    /** @test */
    public function it_cannot_start_trial_with_invalid_duration(): void
    {
        $this->assertFalse($this->user->canStartTrial($this->plan->id, 1)); // Below minimum
        $this->assertFalse($this->user->canStartTrial($this->plan->id, 100)); // Above maximum
    }

    /** @test */
    public function it_cannot_start_trial_if_already_used_trial_for_plan(): void
    {
        // Set config to not allow multiple trials per plan
        config(['sub-sphere.trial.allow_multiple_trials_per_plan' => false]);

        // Create past trial subscription
        Subscription::create([
            'subscriber_type' => get_class($this->user),
            'subscriber_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::EXPIRED,
            'starts_at' => now()->subDays(45),
            'ends_at' => now()->subDays(15),
            'trial_ends_at' => now()->subDays(31), // Had a trial
        ]);

        $this->assertFalse($this->user->canStartTrial($this->plan->id));
    }

    /** @test */
    public function it_can_cancel_active_subscription(): void
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

        $this->assertTrue($this->user->canCancelSubscription());
    }

    /** @test */
    public function it_cannot_cancel_when_no_active_subscription(): void
    {
        $this->assertFalse($this->user->canCancelSubscription());
    }

    /** @test */
    public function it_can_resume_canceled_subscription(): void
    {
        // Create canceled subscription that's still within the paid period
        Subscription::create([
            'subscriber_type' => get_class($this->user),
            'subscriber_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::CANCELED,
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->addDays(20), // Still has time left
        ]);

        $this->assertTrue($this->user->canResumeSubscription());
    }

    /** @test */
    public function it_cannot_resume_when_no_canceled_subscription(): void
    {
        $this->assertFalse($this->user->canResumeSubscription());
    }

    /** @test */
    public function it_can_renew_active_subscription(): void
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

        $this->assertTrue($this->user->canRenewSubscription());
    }

    /** @test */
    public function it_cannot_renew_when_no_active_subscription(): void
    {
        $this->assertFalse($this->user->canRenewSubscription());
    }

    /** @test */
    public function it_can_consume_feature_when_subscription_active(): void
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

        $this->assertTrue($this->user->canConsumeFeature('api-calls', 1));
    }

    /** @test */
    public function it_cannot_consume_feature_with_invalid_amount(): void
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

        $this->assertFalse($this->user->canConsumeFeature('api-calls', 0));
        $this->assertFalse($this->user->canConsumeFeature('api-calls', -1));
    }

    /** @test */
    public function it_can_upgrade_to_different_plan(): void
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

        $this->assertTrue($this->user->canUpgrade($this->otherPlan->id));
    }

    /** @test */
    public function it_cannot_upgrade_to_same_plan(): void
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

        $this->assertFalse($this->user->canUpgrade($this->plan->id));
    }

    /** @test */
    public function it_can_downgrade_when_allowed(): void
    {
        config(['sub-sphere.plan_changes.allow_downgrades' => true]);

        // Create active subscription
        Subscription::create([
            'subscriber_type' => get_class($this->user),
            'subscriber_id' => $this->user->id,
            'plan_id' => $this->otherPlan->id, // Start with premium
            'plan_pricing_id' => $this->otherPricing->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        $this->assertTrue($this->user->canDowngrade($this->plan->id)); // Downgrade to basic
    }

    /** @test */
    public function it_cannot_downgrade_when_disabled(): void
    {
        config(['sub-sphere.plan_changes.allow_downgrades' => false]);

        // Create active subscription
        Subscription::create([
            'subscriber_type' => get_class($this->user),
            'subscriber_id' => $this->user->id,
            'plan_id' => $this->otherPlan->id, // Start with premium
            'plan_pricing_id' => $this->otherPricing->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        $this->assertFalse($this->user->canDowngrade($this->plan->id));
    }

    /** @test */
    public function it_can_access_feature_with_active_subscription(): void
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

        $this->assertTrue($this->user->canAccessFeature('api-calls'));
    }

    /** @test */
    public function it_cannot_access_feature_without_active_subscription(): void
    {
        $this->assertFalse($this->user->canAccessFeature('api-calls'));
    }
}
