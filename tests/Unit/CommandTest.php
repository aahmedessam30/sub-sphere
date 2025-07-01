<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Tests\Unit;

use AhmedEssam\SubSphere\Enums\SubscriptionStatus;
use AhmedEssam\SubSphere\Models\Plan;
use AhmedEssam\SubSphere\Models\PlanFeature;
use AhmedEssam\SubSphere\Models\PlanPricing;
use AhmedEssam\SubSphere\Models\SubscriptionUsage;
use AhmedEssam\SubSphere\Services\SubscriptionService;
use AhmedEssam\SubSphere\Tests\Models\User;
use AhmedEssam\SubSphere\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Test for SubscriptionService actions and command functionality
 * Tests various service methods for subscription management
 */
class CommandTest extends TestCase
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
            'price' => 19.99,
        ]);

        $this->feature = PlanFeature::create([
            'plan_id' => $this->plan->id,
            'key' => 'api_calls',
            'value' => '1000',
            'reset_period' => 'monthly',
        ]);

        $this->subscriptionService = app(SubscriptionService::class);
    }

    /** @test */
    public function it_can_cancel_subscription(): void
    {
        // Create active subscription
        $subscription = $this->user->subscriptions()->create([
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        // Cancel the subscription
        $canceledSubscription = $this->subscriptionService->cancel($subscription);

        $this->assertEquals(SubscriptionStatus::CANCELED, $canceledSubscription->status);
        $this->assertNotNull($canceledSubscription->grace_ends_at);
    }

    /** @test */
    public function it_can_resume_canceled_subscription(): void
    {
        // Create canceled subscription
        $subscription = $this->user->subscriptions()->create([
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::CANCELED,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
            'grace_ends_at' => now()->addDays(3),
        ]);

        // Resume the subscription
        $resumedSubscription = $this->subscriptionService->resume($subscription);

        $this->assertEquals(SubscriptionStatus::ACTIVE, $resumedSubscription->status);
        $this->assertNull($resumedSubscription->grace_ends_at);
    }

    /** @test */
    public function it_can_renew_subscription(): void
    {
        // Create expired subscription
        $subscription = $this->user->subscriptions()->create([
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::EXPIRED,
            'starts_at' => now()->subDays(35),
            'ends_at' => now()->subDays(5),
        ]);

        $oldEndDate = $subscription->ends_at;

        // Renew the subscription
        $renewedSubscription = $this->subscriptionService->renew($subscription);

        $this->assertEquals(SubscriptionStatus::ACTIVE, $renewedSubscription->status);
        $this->assertTrue($renewedSubscription->ends_at->greaterThan($oldEndDate));
    }

    /** @test */
    public function it_can_expire_subscription(): void
    {
        // Create subscription
        $subscription = $this->user->subscriptions()->create([
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now()->subDays(30),
            'ends_at' => now()->subDay(),
        ]);

        // Expire the subscription
        $expiredSubscription = $this->subscriptionService->expire($subscription);

        $this->assertEquals(SubscriptionStatus::EXPIRED, $expiredSubscription->status);
    }

    /** @test */
    public function it_can_reset_feature_usage(): void
    {
        // Create subscription
        $subscription = $this->user->subscriptions()->create([
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        // Create usage record
        SubscriptionUsage::create([
            'subscription_id' => $subscription->id,
            'key' => 'api_calls',
            'used' => 500,
        ]);

        // Reset the feature
        $result = $this->subscriptionService->resetFeature($this->user, 'api_calls');

        $this->assertTrue($result);

        // Check that usage was reset
        $usage = SubscriptionUsage::where('subscription_id', $subscription->id)
            ->where('key', 'api_calls')
            ->first();

        $this->assertEquals(0, $usage->used);
    }

    /** @test */
    public function it_can_get_subscription_collection(): void
    {
        // Create multiple subscriptions
        $subscription1 = $this->user->subscriptions()->create([
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::EXPIRED,
            'starts_at' => now()->subDays(60),
            'ends_at' => now()->subDays(30),
        ]);

        $subscription2 = $this->user->subscriptions()->create([
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        $subscriptions = $this->subscriptionService->getSubscriptions($this->user);

        $this->assertCount(2, $subscriptions);
        $this->assertTrue($subscriptions->contains($subscription1));
        $this->assertTrue($subscriptions->contains($subscription2));
    }

    /** @test */
    public function it_returns_null_for_inactive_subscription(): void
    {
        // Create expired subscription
        $this->user->subscriptions()->create([
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::EXPIRED,
            'starts_at' => now()->subDays(60),
            'ends_at' => now()->subDays(30),
        ]);

        $activeSubscription = $this->subscriptionService->getActiveSubscription($this->user);

        $this->assertNull($activeSubscription);
    }

    /** @test */
    public function it_handles_feature_reset_for_non_existent_subscription(): void
    {
        $userWithoutSubscription = User::create([
            'name' => 'No Sub User',
            'email' => 'nosub@example.com',
        ]);

        $result = $this->subscriptionService->resetFeature($userWithoutSubscription, 'api_calls');

        $this->assertFalse($result);
    }

    /** @test */
    public function it_validates_subscription_states_correctly(): void
    {
        // Test active subscription
        $activeSubscription = $this->user->subscriptions()->create([
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        $this->assertNotNull($this->subscriptionService->getActiveSubscription($this->user));

        // Test trial subscription
        $activeSubscription->update(['status' => SubscriptionStatus::TRIAL]);
        $this->assertNotNull($this->subscriptionService->getActiveSubscription($this->user));

        // Test expired subscription
        $activeSubscription->update(['status' => SubscriptionStatus::EXPIRED]);
        $this->assertNull($this->subscriptionService->getActiveSubscription($this->user));
    }
}
