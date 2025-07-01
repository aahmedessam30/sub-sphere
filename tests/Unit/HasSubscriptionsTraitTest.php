<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Tests\Unit;

use AhmedEssam\SubSphere\Enums\SubscriptionStatus;
use AhmedEssam\SubSphere\Models\Plan;
use AhmedEssam\SubSphere\Models\PlanPricing;
use AhmedEssam\SubSphere\Models\Subscription;
use AhmedEssam\SubSphere\Tests\Models\User;
use AhmedEssam\SubSphere\Tests\TestCase;

class HasSubscriptionsTraitTest extends TestCase
{
    private User $user;
    private Plan $plan;
    private PlanPricing $pricing;

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

        $this->pricing = PlanPricing::create([
            'plan_id' => $this->plan->id,
            'label' => 'Monthly',
            'duration_in_days' => 30,
            'price' => 9.99,
        ]);
    }

    /** @test */
    public function it_has_subscriptions_relationship(): void
    {
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\MorphMany::class,
            $this->user->subscriptions()
        );
    }

    /** @test */
    public function it_can_create_subscription(): void
    {
        $subscription = $this->user->subscriptions()->create([
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        $this->assertInstanceOf(Subscription::class, $subscription);
        $this->assertEquals($this->user->id, $subscription->subscriber_id);
        $this->assertEquals(User::class, $subscription->subscriber_type);
    }

    /** @test */
    public function it_returns_active_subscription(): void
    {
        // Create an active subscription
        $activeSubscription = $this->user->subscriptions()->create([
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        // Create an expired subscription
        $this->user->subscriptions()->create([
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::EXPIRED,
            'starts_at' => now()->subDays(60),
            'ends_at' => now()->subDays(30),
        ]);

        $result = $this->user->activeSubscription();

        $this->assertNotNull($result);
        $this->assertEquals($activeSubscription->id, $result->id);
        $this->assertEquals(SubscriptionStatus::ACTIVE, $result->status);
    }

    /** @test */
    public function it_returns_null_when_no_active_subscription(): void
    {
        // Create only expired subscriptions
        $this->user->subscriptions()->create([
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::EXPIRED,
            'starts_at' => now()->subDays(60),
            'ends_at' => now()->subDays(30),
        ]);

        $result = $this->user->activeSubscription();

        $this->assertNull($result);
    }

    /** @test */
    public function it_can_check_if_user_has_active_subscription(): void
    {
        // No subscription initially
        $this->assertFalse($this->user->hasActiveSubscription());

        // Create active subscription
        $this->user->subscriptions()->create([
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        $this->assertTrue($this->user->hasActiveSubscription());
    }

    /** @test */
    public function it_can_get_subscription_history(): void
    {
        // Create multiple subscriptions
        $subscription1 = $this->user->subscriptions()->create([
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::EXPIRED,
            'starts_at' => now()->subDays(60),
            'ends_at' => now()->subDays(30),
            'created_at' => now()->subMinute(), // Ensure different timestamps
        ]);

        $subscription2 = $this->user->subscriptions()->create([
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
            'created_at' => now(), // Most recent
        ]);

        $history = $this->user->subscriptionHistory();

        $this->assertCount(2, $history);
        $this->assertEquals($subscription2->id, $history->first()->id); // Most recent first
    }

    /** @test */
    public function it_handles_trial_subscriptions(): void
    {
        $trialSubscription = $this->user->subscriptions()->create([
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::TRIAL,
            'starts_at' => now(),
            'trial_ends_at' => now()->addDays(7),
            'ends_at' => now()->addDays(30),
        ]);

        $this->assertTrue($this->user->hasActiveSubscription());
        $this->assertEquals($trialSubscription->id, $this->user->activeSubscription()->id);
    }
}
