<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Tests\Unit;

use AhmedEssam\SubSphere\Enums\SubscriptionStatus;
use AhmedEssam\SubSphere\Models\Plan;
use AhmedEssam\SubSphere\Models\PlanPricing;
use AhmedEssam\SubSphere\Models\Subscription;
use AhmedEssam\SubSphere\Services\SubscriptionService;
use AhmedEssam\SubSphere\Tests\Models\User;
use AhmedEssam\SubSphere\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Test SubscriptionService enhanced functionality
 * Tests health monitoring, statistics, and helper methods
 */
class SubscriptionServiceEnhancedTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionService $subscriptionService;
    private User $user;
    private Plan $plan;
    private PlanPricing $pricing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionService = app(SubscriptionService::class);

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
    }

    /** @test */
    public function it_can_get_active_subscription_for_user(): void
    {
        // Create active subscription
        $subscription = Subscription::create([
            'subscriber_type' => get_class($this->user),
            'subscriber_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        $activeSubscription = $this->subscriptionService->getActiveSubscription($this->user);

        $this->assertNotNull($activeSubscription);
        $this->assertEquals($subscription->id, $activeSubscription->id);
    }

    /** @test */
    public function it_returns_null_when_no_active_subscription(): void
    {
        $activeSubscription = $this->subscriptionService->getActiveSubscription($this->user);
        $this->assertNull($activeSubscription);
    }

    /** @test */
    public function it_can_get_all_subscriptions_for_user(): void
    {
        // Create multiple subscriptions
        $subscription1 = Subscription::create([
            'subscriber_type' => get_class($this->user),
            'subscriber_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::EXPIRED,
            'starts_at' => now()->subDays(60),
            'ends_at' => now()->subDays(30),
        ]);

        $subscription2 = Subscription::create([
            'subscriber_type' => get_class($this->user),
            'subscriber_id' => $this->user->id,
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
    public function it_can_check_if_user_has_any_subscription(): void
    {
        $this->assertFalse($this->subscriptionService->hasAnySubscription($this->user));

        Subscription::create([
            'subscriber_type' => get_class($this->user),
            'subscriber_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::EXPIRED,
            'starts_at' => now()->subDays(60),
            'ends_at' => now()->subDays(30),
        ]);

        $this->assertTrue($this->subscriptionService->hasAnySubscription($this->user));
    }

    /** @test */
    public function it_can_get_subscription_by_id(): void
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

        $found = $this->subscriptionService->getSubscriptionById($this->user, $subscription->id);

        $this->assertNotNull($found);
        $this->assertEquals($subscription->id, $found->id);
    }

    /** @test */
    public function it_returns_null_for_non_existent_subscription_id(): void
    {
        $found = $this->subscriptionService->getSubscriptionById($this->user, 99999);
        $this->assertNull($found);
    }

    /** @test */
    public function it_can_get_health_status(): void
    {
        // Create test data
        Subscription::create([
            'subscriber_type' => get_class($this->user),
            'subscriber_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        Subscription::create([
            'subscriber_type' => get_class($this->user),
            'subscriber_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::TRIAL,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
            'trial_ends_at' => now()->addDays(7),
        ]);

        $health = $this->subscriptionService->getHealthStatus();

        $this->assertArrayHasKey('status', $health);
        $this->assertArrayHasKey('active_subscriptions', $health);
        $this->assertArrayHasKey('expiring_soon', $health);
        $this->assertArrayHasKey('overdue_subscriptions', $health);
        $this->assertArrayHasKey('auto_renewal_enabled', $health);

        $this->assertEquals(2, $health['active_subscriptions']); // ACTIVE + TRIAL
    }

    /** @test */
    public function it_can_get_subscription_statistics(): void
    {
        // Create test data for statistics
        Subscription::create([
            'subscriber_type' => get_class($this->user),
            'subscriber_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        Subscription::create([
            'subscriber_type' => get_class($this->user),
            'subscriber_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::TRIAL,
            'trial_ends_at' => now()->addDays(7),
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        $stats = $this->subscriptionService->getSubscriptionStatistics();

        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('active', $stats);
        $this->assertArrayHasKey('expired', $stats);
        $this->assertArrayHasKey('trial', $stats);
        $this->assertArrayHasKey('canceled', $stats);

        $this->assertEquals(2, $stats['total']);
        $this->assertEquals(1, $stats['active']);
        $this->assertEquals(1, $stats['trial']);
        $this->assertEquals(0, $stats['canceled']);
        $this->assertEquals(0, $stats['expired']);
    }

    /** @test */
    public function it_can_expire_overdue_subscriptions(): void
    {
        // Create overdue subscription
        $overdueSubscription = Subscription::create([
            'subscriber_type' => get_class($this->user),
            'subscriber_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now()->subDays(60),
            'ends_at' => now()->subDays(10), // Expired 10 days ago
        ]);

        // Create normal subscription
        $normalSubscription = Subscription::create([
            'subscriber_type' => get_class($this->user),
            'subscriber_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        $expiredCount = $this->subscriptionService->expireOverdueSubscriptions();

        $this->assertEquals(1, $expiredCount);

        // Check that overdue subscription was expired
        $overdueSubscription->refresh();
        $this->assertEquals(SubscriptionStatus::EXPIRED, $overdueSubscription->status);

        // Check that normal subscription is still active
        $normalSubscription->refresh();
        $this->assertEquals(SubscriptionStatus::ACTIVE, $normalSubscription->status);
    }

    /** @test */
    public function it_can_process_auto_renewals(): void
    {
        // Create subscription eligible for auto-renewal
        $subscription = Subscription::create([
            'subscriber_type' => get_class($this->user),
            'subscriber_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::ACTIVE,
            'is_auto_renewal' => true,
            'starts_at' => now()->subDays(30),
            'ends_at' => now()->subDay(), // Expired yesterday
        ]);

        $renewedCount = $this->subscriptionService->autoRenewEligibleSubscriptions();

        $this->assertEquals(1, $renewedCount);

        // Check that subscription was renewed (end date extended)
        $subscription->refresh();
        $this->assertTrue($subscription->ends_at->isFuture());
    }

    /** @test */
    public function it_does_not_auto_renew_subscriptions_without_auto_renewal_flag(): void
    {
        // Create subscription without auto-renewal
        Subscription::create([
            'subscriber_type' => get_class($this->user),
            'subscriber_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::ACTIVE,
            'is_auto_renewal' => false,
            'starts_at' => now()->subDays(30),
            'ends_at' => now()->subDay(),
        ]);

        $renewedCount = $this->subscriptionService->autoRenewEligibleSubscriptions();

        $this->assertEquals(0, $renewedCount);
    }

    /** @test */
    public function it_validates_feature_consumption_before_processing(): void
    {
        // Should return false when no active subscription exists
        $result = $this->subscriptionService->consumeFeature($this->user, 'api_calls', 1);
        $this->assertFalse($result);
    }

    /** @test */
    public function it_throws_exception_for_empty_feature_key(): void
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
        $this->expectExceptionMessage('Feature key cannot be empty');

        $this->subscriptionService->consumeFeature($this->user, '', 1);
    }

    /** @test */
    public function it_throws_exception_for_negative_consumption_amount(): void
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
        $this->expectExceptionMessage('Feature consumption amount must be positive.');

        $this->subscriptionService->consumeFeature($this->user, 'api_calls', 0);
    }

    /** @test */
    public function it_tracks_total_counts_in_statistics(): void
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

        // Create canceled subscription
        Subscription::create([
            'subscriber_type' => get_class($this->user),
            'subscriber_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::CANCELED,
            'starts_at' => now()->subDays(30),
            'ends_at' => now()->addDays(5),
        ]);

        $stats = $this->subscriptionService->getSubscriptionStatistics();

        $this->assertEquals(2, $stats['total']); // 2 from this test
        $this->assertEquals(1, $stats['active']); // 1 from this test
        $this->assertEquals(1, $stats['canceled']); // From this test
    }

    /** @test */
    public function it_counts_overdue_subscriptions_correctly(): void
    {
        // Create overdue subscription (expired with no grace period)
        Subscription::create([
            'subscriber_type' => get_class($this->user),
            'subscriber_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now()->subDays(60),
            'ends_at' => now()->subDays(10),
            'grace_ends_at' => null,
        ]);

        // Create subscription in grace period (not overdue)
        Subscription::create([
            'subscriber_type' => get_class($this->user),
            'subscriber_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now()->subDays(35),
            'ends_at' => now()->subDays(5),
            'grace_ends_at' => now()->addDays(2), // Still in grace
        ]);

        $health = $this->subscriptionService->getHealthStatus();

        $this->assertEquals(1, $health['overdue_subscriptions']);
    }
}