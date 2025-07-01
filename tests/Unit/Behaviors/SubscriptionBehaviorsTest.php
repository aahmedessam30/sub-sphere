<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Tests\Unit\Behaviors;

use AhmedEssam\SubSphere\Enums\SubscriptionStatus;
use AhmedEssam\SubSphere\Models\Plan;
use AhmedEssam\SubSphere\Models\PlanFeature;
use AhmedEssam\SubSphere\Models\PlanPricing;
use AhmedEssam\SubSphere\Models\Subscription;
use AhmedEssam\SubSphere\Models\SubscriptionUsage;
use AhmedEssam\SubSphere\Tests\TestCase;
use AhmedEssam\SubSphere\Tests\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SubscriptionBehaviorsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Plan $plan;
    protected PlanPricing $pricing;
    protected Subscription $subscription;
    protected PlanFeature $feature;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->plan = Plan::create([
            'name' => 'Basic Plan',
            'slug' => 'basic-plan',
            'description' => 'A basic plan',
            'is_active' => true,
        ]);

        $this->pricing = PlanPricing::create([
            'plan_id' => $this->plan->id,
            'label' => 'Monthly',
            'price' => 9.99,
            'currency' => 'USD',
            'duration_in_days' => 30,
        ]);

        $this->subscription = Subscription::create([
            'subscriber_type' => User::class,
            'subscriber_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        $this->feature = PlanFeature::create([
            'plan_id' => $this->plan->id,
            'key' => 'api_calls',
            'value' => '100',
            'reset_period' => 'monthly',
        ]);
    }

    /** @test */
    public function it_can_activate_subscription(): void
    {
        $inactiveSubscription = Subscription::create([
            'subscriber_type' => User::class,
            'subscriber_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::INACTIVE,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        $result = $inactiveSubscription->activate();

        $this->assertTrue($result);
        $this->assertEquals(SubscriptionStatus::ACTIVE, $inactiveSubscription->fresh()->status);
    }

    /** @test */
    public function it_can_cancel_subscription(): void
    {
        $result = $this->subscription->cancel();

        $this->assertTrue($result);
        $this->assertEquals(SubscriptionStatus::CANCELED, $this->subscription->fresh()->status);
    }

    /** @test */
    public function it_can_expire_subscription(): void
    {
        $result = $this->subscription->expire();

        $this->assertTrue($result);
        $this->assertEquals(SubscriptionStatus::EXPIRED, $this->subscription->fresh()->status);
    }

    /** @test */
    public function it_can_resume_canceled_subscription(): void
    {
        $this->subscription->cancel();
        $result = $this->subscription->fresh()->resume();

        $this->assertTrue($result);
        $this->assertEquals(SubscriptionStatus::ACTIVE, $this->subscription->fresh()->status);
    }

    /** @test */
    public function it_can_renew_subscription(): void
    {
        $originalEndDate = $this->subscription->ends_at;
        $result = $this->subscription->renew();

        $this->assertTrue($result);
        $this->assertGreaterThan($originalEndDate, $this->subscription->fresh()->ends_at);
    }

    /** @test */
    public function it_checks_subscription_status_correctly(): void
    {
        $this->assertTrue($this->subscription->isActive());
        $this->assertFalse($this->subscription->isCanceled());
        $this->assertFalse($this->subscription->isExpired());
        $this->assertFalse($this->subscription->isInactive());

        $this->subscription->cancel();
        $this->assertTrue($this->subscription->fresh()->isCanceled());
    }

    /** @test */
    public function it_determines_trial_status_correctly(): void
    {
        $trialSubscription = Subscription::create([
            'subscriber_type' => User::class,
            'subscriber_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::TRIAL,
            'trial_ends_at' => now()->addDays(7),
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        $this->assertTrue($trialSubscription->onTrial());
        $this->assertFalse($this->subscription->onTrial());
    }

    /** @test */
    public function it_calculates_remaining_days_correctly(): void
    {
        $daysRemaining = $this->subscription->getRemainingDays();
        $this->assertGreaterThan(0, $daysRemaining);
        $this->assertLessThanOrEqual(30, $daysRemaining);
    }

    /** @test */
    public function it_checks_if_subscription_is_ending_soon(): void
    {
        // Set subscription to end in 3 days
        $this->subscription->update(['ends_at' => now()->addDays(3)]);
        $this->assertTrue($this->subscription->isEndingSoon());

        // Set subscription to end in 10 days
        $this->subscription->update(['ends_at' => now()->addDays(10)]);
        $this->assertFalse($this->subscription->isEndingSoon());
    }

    /** @test */
    public function it_can_consume_feature_usage(): void
    {
        $result = $this->subscription->consumeFeature('api_calls', 5);
        $this->assertTrue($result);

        $usage = SubscriptionUsage::where([
            'subscription_id' => $this->subscription->id,
            'key' => 'api_calls'
        ])->first();

        $this->assertEquals(5, $usage->used);
    }

    /** @test */
    public function it_prevents_overconsumption_of_limited_features(): void
    {
        // Use 95 of 100 API calls
        $this->subscription->consumeFeature('api_calls', 95);

        // Try to use 10 more (should fail)
        $result = $this->subscription->consumeFeature('api_calls', 10);
        $this->assertFalse($result);

        // Usage should still be 95
        $usage = SubscriptionUsage::where([
            'subscription_id' => $this->subscription->id,
            'key' => 'api_calls'
        ])->first();

        $this->assertEquals(95, $usage->used);
    }

    /** @test */
    public function it_allows_unlimited_feature_consumption(): void
    {
        // Create unlimited feature
        $unlimitedFeature = PlanFeature::create([
            'plan_id' => $this->plan->id,
            'key' => 'unlimited_feature',
            'value' => 'unlimited', // unlimited
            'reset_period' => 'monthly',
        ]);

        // Should allow any amount
        $result = $this->subscription->consumeFeature('unlimited_feature', 1000);
        $this->assertTrue($result);

        $usage = SubscriptionUsage::where([
            'subscription_id' => $this->subscription->id,
            'key' => 'unlimited_feature'
        ])->first();

        $this->assertEquals(1000, $usage->used);
    }

    /** @test */
    public function it_gets_remaining_usage_correctly(): void
    {
        $this->subscription->consumeFeature('api_calls', 30);

        $remaining = $this->subscription->getRemainingUsage('api_calls');
        $this->assertEquals(70, $remaining);
    }

    /** @test */
    public function it_returns_null_for_unlimited_feature_remaining_usage(): void
    {
        $unlimitedFeature = PlanFeature::create([
            'plan_id' => $this->plan->id,
            'key' => 'unlimited_feature',
            'value' => 'unlimited',
            'reset_period' => 'monthly',
        ]);

        $remaining = $this->subscription->getRemainingUsage('unlimited_feature');
        $this->assertNull($remaining);
    }

    /** @test */
    public function it_gets_feature_value_correctly(): void
    {
        $value = $this->subscription->getFeatureValue('api_calls');
        $this->assertEquals(100, $value);
    }

    /** @test */
    public function it_returns_null_for_nonexistent_feature(): void
    {
        $value = $this->subscription->getFeatureValue('nonexistent');
        $this->assertNull($value);
    }

    /** @test */
    public function it_can_check_if_subscription_has_feature(): void
    {
        $this->assertTrue($this->subscription->hasFeature('api_calls'));
        $this->assertFalse($this->subscription->hasFeature('nonexistent'));
    }

    /** @test */
    public function it_resets_usage_when_period_expires(): void
    {
        // Create a daily reset feature
        $dailyFeature = PlanFeature::create([
            'plan_id' => $this->plan->id,
            'key' => 'daily_feature',
            'value' => '10',
            'reset_period' => 'daily',
        ]);

        // Use some of the feature
        $this->subscription->consumeFeature('daily_feature', 5);

        // Update the usage to be from yesterday
        SubscriptionUsage::where([
            'subscription_id' => $this->subscription->id,
            'key' => 'daily_feature'
        ])->update(['last_used_at' => now()->subDay()]);

        // Consume again - should reset and allow full usage
        $result = $this->subscription->consumeFeature('daily_feature', 8);
        $this->assertTrue($result);

        $usage = SubscriptionUsage::where([
            'subscription_id' => $this->subscription->id,
            'key' => 'daily_feature'
        ])->first();

        $this->assertEquals(8, $usage->used);
    }
}
