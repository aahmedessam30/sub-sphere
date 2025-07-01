<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Tests\Unit;

use AhmedEssam\SubSphere\Enums\SubscriptionStatus;
use AhmedEssam\SubSphere\Models\Plan;
use AhmedEssam\SubSphere\Models\PlanFeature;
use AhmedEssam\SubSphere\Models\PlanPricing;
use AhmedEssam\SubSphere\Services\SubscriptionService;
use AhmedEssam\SubSphere\Tests\Models\User;
use AhmedEssam\SubSphere\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Test FeatureUsage tracking functionality
 * Tests feature access and basic consumption through SubscriptionService
 */
class FeatureUsageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Plan $plan;
    private PlanPricing $pricing;
    private PlanFeature $feature;
    private SubscriptionService $subscriptionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->plan = $this->createTestPlan([
            'slug' => 'business-plan',
            'name' => ['en' => 'Business Plan'],
            'description' => ['en' => 'A business subscription plan with features'],
        ]);

        $this->pricing = PlanPricing::create([
            'plan_id' => $this->plan->id,
            'label' => 'Monthly',
            'duration_in_days' => 30,
            'price' => 49.99,
        ]);

        $this->feature = PlanFeature::create([
            'plan_id' => $this->plan->id,
            'key' => 'api_calls',
            'value' => '1000',
            'reset_period' => 'monthly',
        ]);

        // Create subscription for user
        $this->user->subscriptions()->create([
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        $this->subscriptionService = app(SubscriptionService::class);
    }

    /** @test */
    public function it_can_check_if_user_has_feature_access(): void
    {
        $this->assertTrue($this->user->hasFeature('api_calls'));
        $this->assertFalse($this->user->hasFeature('non_existent_feature'));
    }

    /** @test */
    public function it_can_get_feature_value(): void
    {
        $value = $this->user->getFeatureValue('api_calls');
        $this->assertEquals(1000, $value);
    }

    /** @test */
    public function it_can_consume_features_using_trait_method(): void
    {
        $consumed = $this->user->consumeFeature('api_calls', 150);
        $this->assertTrue($consumed);
    }

    /** @test */
    public function it_can_consume_features_using_service(): void
    {
        $consumed = $this->subscriptionService->consumeFeature($this->user, 'api_calls', 200);
        $this->assertTrue($consumed);
    }

    /** @test */
    public function it_handles_unlimited_features(): void
    {
        // Create unlimited feature
        PlanFeature::create([
            'plan_id' => $this->plan->id,
            'key' => 'storage',
            'value' => 'unlimited',
            'reset_period' => 'never',
        ]);

        $this->assertTrue($this->user->hasFeature('storage'));

        // Should be able to consume any amount
        $consumed = $this->subscriptionService->consumeFeature($this->user, 'storage', 999999);
        $this->assertTrue($consumed);
    }

    /** @test */
    public function it_handles_boolean_features(): void
    {
        // Create boolean feature
        PlanFeature::create([
            'plan_id' => $this->plan->id,
            'key' => 'premium_support',
            'value' => 'true',
            'reset_period' => 'never',
        ]);

        $this->assertTrue($this->user->hasFeature('premium_support'));
        $this->assertTrue($this->user->getFeatureValue('premium_support'));
    }

    /** @test */
    public function it_returns_false_for_features_without_active_subscription(): void
    {
        // Expire the subscription
        $subscription = $this->user->activeSubscription();
        $subscription->update([
            'status' => SubscriptionStatus::EXPIRED,
            'ends_at' => now()->subDay(),
        ]);

        $this->assertFalse($this->user->hasFeature('api_calls'));
        $this->assertFalse($this->subscriptionService->consumeFeature($this->user, 'api_calls', 1));
    }

    /** @test */
    public function it_can_use_subscription_service_methods(): void
    {
        // Test hasFeature through service
        $this->assertTrue($this->subscriptionService->hasFeature($this->user, 'api_calls'));

        // Test getFeatureValue through service
        $value = $this->subscriptionService->getFeatureValue($this->user, 'api_calls');
        $this->assertEquals(1000, $value);

        // Test consumeFeature through service
        $consumed = $this->subscriptionService->consumeFeature($this->user, 'api_calls', 50);
        $this->assertTrue($consumed);
    }

    /** @test */
    public function it_prevents_feature_access_for_non_existent_features(): void
    {
        $this->assertFalse($this->subscriptionService->hasFeature($this->user, 'non_existent'));
        $this->assertNull($this->subscriptionService->getFeatureValue($this->user, 'non_existent'));
        $this->assertFalse($this->subscriptionService->consumeFeature($this->user, 'non_existent', 1));
    }

    /** @test */
    public function it_handles_users_without_subscription(): void
    {
        $userWithoutSubscription = User::create([
            'name' => 'No Sub User',
            'email' => 'nosub@example.com',
        ]);

        $this->assertFalse($this->subscriptionService->hasFeature($userWithoutSubscription, 'api_calls'));
        $this->assertNull($this->subscriptionService->getFeatureValue($userWithoutSubscription, 'api_calls'));
        $this->assertFalse($this->subscriptionService->consumeFeature($userWithoutSubscription, 'api_calls', 1));
    }
}
