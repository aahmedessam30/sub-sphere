<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Tests\Integration;

use AhmedEssam\SubSphere\Enums\SubscriptionStatus;
use AhmedEssam\SubSphere\Models\Plan;
use AhmedEssam\SubSphere\Models\PlanPricing;
use AhmedEssam\SubSphere\Models\Subscription;
use AhmedEssam\SubSphere\Services\SubscriptionService;
use AhmedEssam\SubSphere\Tests\Models\User;
use AhmedEssam\SubSphere\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Integration test for creating subscriptions
 * Tests the full flow of subscription creation including business logic
 */
class CreateSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Plan $plan;
    private PlanPricing $pricing;
    private SubscriptionService $subscriptionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->plan = $this->createTestPlan([
            'slug' => 'premium-plan',
            'name' => ['en' => 'Premium Plan'],
            'description' => ['en' => 'A premium subscription plan'],
        ]);

        $this->pricing = PlanPricing::create([
            'plan_id' => $this->plan->id,
            'label' => 'Monthly',
            'duration_in_days' => 30,
            'price' => 19.99,
        ]);

        $this->subscriptionService = app(SubscriptionService::class);
    }

    /** @test */
    public function it_can_create_a_subscription_for_user(): void
    {
        $subscription = $this->subscriptionService->subscribe(
            $this->user,
            $this->plan->id,
            $this->pricing->id
        );

        $this->assertInstanceOf(Subscription::class, $subscription);
        $this->assertEquals($this->user->id, $subscription->subscriber_id);
        $this->assertEquals(User::class, $subscription->subscriber_type);
        $this->assertEquals($this->plan->id, $subscription->plan_id);
        $this->assertEquals($this->pricing->id, $subscription->plan_pricing_id);
        $this->assertEquals(SubscriptionStatus::ACTIVE, $subscription->status);
        $this->assertNotNull($subscription->starts_at);
        $this->assertNotNull($subscription->ends_at);
        $this->assertNull($subscription->trial_ends_at);
    }

    /** @test */
    public function it_can_create_a_trial_subscription(): void
    {
        $trialDays = 14;

        $subscription = $this->subscriptionService->subscribe(
            $this->user,
            $this->plan->id,
            $this->pricing->id,
            $trialDays
        );

        $this->assertEquals(SubscriptionStatus::TRIAL, $subscription->status);
        $this->assertNotNull($subscription->trial_ends_at);
        $this->assertEquals(
            now()->addDays($trialDays)->toDateString(),
            $subscription->trial_ends_at->toDateString()
        );
    }

    /** @test */
    public function it_prevents_creating_multiple_active_subscriptions(): void
    {
        // Create first subscription
        $this->subscriptionService->subscribe(
            $this->user,
            $this->plan->id,
            $this->pricing->id
        );

        // Try to create another subscription
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Subscriber already has an active subscription');

        $this->subscriptionService->subscribe(
            $this->user,
            $this->plan->id,
            $this->pricing->id
        );
    }

    /** @test */
    public function it_can_create_subscription_after_previous_expires(): void
    {
        // Create first subscription that expires
        $firstSubscription = $this->subscriptionService->subscribe(
            $this->user,
            $this->plan->id,
            $this->pricing->id
        );

        // Manually expire the subscription
        $firstSubscription->update([
            'status' => SubscriptionStatus::EXPIRED,
            'ends_at' => now()->subDay(),
        ]);

        // Should be able to create new subscription
        $newSubscription = $this->subscriptionService->subscribe(
            $this->user,
            $this->plan->id,
            $this->pricing->id
        );

        $this->assertNotEquals($firstSubscription->id, $newSubscription->id);
        $this->assertEquals(SubscriptionStatus::ACTIVE, $newSubscription->status);
    }

    /** @test */
    public function it_creates_subscription_with_correct_dates(): void
    {
        $subscription = $this->subscriptionService->subscribe(
            $this->user,
            $this->plan->id,
            $this->pricing->id
        );

        $expectedEndDate = now()->addDays($this->pricing->duration_in_days);

        $this->assertEquals(
            now()->toDateString(),
            $subscription->starts_at->toDateString()
        );
        $this->assertEquals(
            $expectedEndDate->toDateString(),
            $subscription->ends_at->toDateString()
        );
    }

    /** @test */
    public function it_can_create_subscription_using_trait_method(): void
    {
        $subscription = $this->user->subscribe(
            $this->plan->id,
            $this->pricing->id
        );

        $this->assertEquals($this->user->id, $subscription->subscriber_id);
        $this->assertEquals(SubscriptionStatus::ACTIVE, $subscription->status);
    }

    /** @test */
    public function it_can_start_trial_using_trait_method(): void
    {
        $trialDays = 7;

        $subscription = $this->user->startTrial(
            $this->plan->id,
            $trialDays
        );

        $this->assertEquals(SubscriptionStatus::TRIAL, $subscription->status);
        $this->assertTrue($this->user->isOnTrial());
    }

    /** @test */
    public function it_validates_plan_exists(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->subscriptionService->subscribe(
            $this->user,
            99999, // Non-existent plan
            $this->pricing->id
        );
    }

    /** @test */
    public function it_validates_pricing_exists(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->subscriptionService->subscribe(
            $this->user,
            $this->plan->id,
            99999 // Non-existent pricing
        );
    }

    /** @test */
    public function it_validates_pricing_belongs_to_plan(): void
    {
        // Create another plan with different pricing
        $anotherPlan = $this->createTestPlan([
            'slug' => 'another-plan',
            'name' => ['en' => 'Another Plan'],
            'description' => ['en' => 'Another plan'],
        ]);

        $anotherPricing = PlanPricing::create([
            'plan_id' => $anotherPlan->id,
            'label' => 'Monthly',
            'duration_in_days' => 30,
            'price' => 29.99,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        // Try to use pricing from another plan
        $this->subscriptionService->subscribe(
            $this->user,
            $this->plan->id,
            $anotherPricing->id
        );
    }
}
