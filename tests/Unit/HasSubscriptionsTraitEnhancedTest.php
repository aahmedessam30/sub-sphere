<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Tests\Unit;

use AhmedEssam\SubSphere\Actions\ChangeSubscriptionPlanAction;
use AhmedEssam\SubSphere\Actions\CancelSubscriptionAction;
use AhmedEssam\SubSphere\Actions\ResumeSubscriptionAction;
use AhmedEssam\SubSphere\Enums\SubscriptionStatus;
use AhmedEssam\SubSphere\Events\SubscriptionChanged;
use AhmedEssam\SubSphere\Events\SubscriptionStarted;
use AhmedEssam\SubSphere\Events\TrialStarted;
use AhmedEssam\SubSphere\Exceptions\CouldNotStartSubscriptionException;
use AhmedEssam\SubSphere\Models\Plan;
use AhmedEssam\SubSphere\Models\PlanPricing;
use AhmedEssam\SubSphere\Models\Subscription;
use AhmedEssam\SubSphere\Tests\Models\User;
use AhmedEssam\SubSphere\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Mockery;

class HasSubscriptionsTraitEnhancedTest extends TestCase
{
    private User $user;
    private Plan $plan;
    private Plan $newPlan;
    private PlanPricing $pricing;
    private PlanPricing $newPricing;

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

        $this->newPlan = $this->createTestPlan([
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

        $this->newPricing = PlanPricing::create([
            'plan_id' => $this->newPlan->id,
            'label' => 'Monthly Premium',
            'duration_in_days' => 30,
            'price' => 19.99,
        ]);
    }

    /** @test */
    public function it_dispatches_subscription_started_event_when_subscribing(): void
    {
        Event::fake();

        $this->user->subscribe($this->plan->id, $this->pricing->id);

        Event::assertDispatched(SubscriptionStarted::class, function ($event) {
            return $event->subscriber->id === $this->user->id &&
                $event->subscription->plan_id === $this->plan->id &&
                $event->isTrial === false;
        });
    }

    /** @test */
    public function it_dispatches_subscription_started_event_with_trial_when_subscribing_with_trial(): void
    {
        Event::fake();

        $this->user->subscribe($this->plan->id, $this->pricing->id, 7);

        Event::assertDispatched(SubscriptionStarted::class, function ($event) {
            return $event->subscriber->id === $this->user->id &&
                $event->subscription->plan_id === $this->plan->id &&
                $event->isTrial === true;
        });
    }

    /** @test */
    public function it_dispatches_trial_started_event_when_starting_trial(): void
    {
        Event::fake();

        $this->user->startTrial($this->plan->id, 14);

        Event::assertDispatched(TrialStarted::class, function ($event) {
            return $event->subscriber->id === $this->user->id &&
                $event->subscription->plan_id === $this->plan->id &&
                $event->trialDays === 14;
        });
    }

    /** @test */
    public function it_throws_custom_exception_when_subscribing_with_active_subscription(): void
    {
        // Create active subscription first
        $this->user->subscriptions()->create([
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        $this->expectException(CouldNotStartSubscriptionException::class);
        $this->expectExceptionMessage('Subscriber already has an active subscription. Cancel or expire existing subscription first.');

        $this->user->subscribe($this->newPlan->id, $this->newPricing->id);
    }

    /** @test */
    public function it_throws_custom_exception_when_starting_trial_with_active_subscription(): void
    {
        // Create active subscription first
        $this->user->subscriptions()->create([
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        $this->expectException(CouldNotStartSubscriptionException::class);
        $this->expectExceptionMessage('Subscriber already has an active subscription. Cancel or expire existing subscription first.');

        $this->user->startTrial($this->newPlan->id, 14);
    }

    /** @test */
    public function it_can_change_plan_successfully(): void
    {
        Event::fake();

        // Create active subscription with proper relationships
        $subscription = $this->user->subscriptions()->create([
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        // Load the relationships properly
        $subscription->load(['plan', 'planPricing']);

        // Mock the ChangeSubscriptionPlanAction
        $mockAction = Mockery::mock(ChangeSubscriptionPlanAction::class);
        $newSubscription = new Subscription([
            'id' => 999,
            'plan_id' => $this->newPlan->id,
            'plan_pricing_id' => $this->newPricing->id,
            'status' => SubscriptionStatus::ACTIVE,
        ]);
        $newSubscription->plan = $this->newPlan;

        $mockAction->shouldReceive('execute')
            ->once()
            ->with($this->user, $this->newPlan->id, $this->pricing->id)
            ->andReturn($newSubscription);

        $this->app->instance(ChangeSubscriptionPlanAction::class, $mockAction);

        $result = $this->user->changePlan($this->newPlan->id);

        $this->assertTrue($result);

        Event::assertDispatched(SubscriptionChanged::class, function ($event) use ($newSubscription) {
            return $event->subscriber->id === $this->user->id &&
                $event->newPlan->id === $this->newPlan->id &&
                $event->oldPlan->id === $this->plan->id &&
                isset($event->changeSummary['reset_usage']) &&
                isset($event->changeSummary['changed_at']);
        });
    }

    /** @test */
    public function it_uses_config_fallback_for_reset_usage_when_null(): void
    {
        // Set config value
        config(['sub-sphere.plan_changes.reset_usage_on_plan_change' => false]);

        // Create active subscription with proper relationships
        $subscription = $this->user->subscriptions()->create([
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        // Load the relationships properly
        $subscription->load(['plan', 'planPricing']);

        // Mock the ChangeSubscriptionPlanAction
        $mockAction = Mockery::mock(ChangeSubscriptionPlanAction::class);
        $newSubscription = new Subscription([
            'id' => 999,
            'plan_id' => $this->newPlan->id,
            'plan_pricing_id' => $this->newPricing->id,
            'status' => SubscriptionStatus::ACTIVE,
        ]);
        $newSubscription->plan = $this->newPlan;

        $mockAction->shouldReceive('execute')
            ->once()
            ->andReturn($newSubscription);

        $this->app->instance(ChangeSubscriptionPlanAction::class, $mockAction);

        Event::fake();

        $result = $this->user->changePlan($this->newPlan->id, null); // null should use config

        $this->assertTrue($result);

        Event::assertDispatched(SubscriptionChanged::class, function ($event) {
            return $event->changeSummary['reset_usage'] === false; // Should use config value
        });
    }

    /** @test */
    public function it_respects_explicit_reset_usage_parameter(): void
    {
        // Set config to opposite of what we'll pass explicitly
        config(['sub-sphere.plan_changes.reset_usage_on_plan_change' => false]);

        // Create active subscription with proper relationships
        $subscription = $this->user->subscriptions()->create([
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        // Load the relationships properly
        $subscription->load(['plan', 'planPricing']);

        // Mock the ChangeSubscriptionPlanAction
        $mockAction = Mockery::mock(ChangeSubscriptionPlanAction::class);
        $newSubscription = new Subscription([
            'id' => 999,
            'plan_id' => $this->newPlan->id,
            'plan_pricing_id' => $this->newPricing->id,
            'status' => SubscriptionStatus::ACTIVE,
        ]);
        $newSubscription->plan = $this->newPlan;

        $mockAction->shouldReceive('execute')
            ->once()
            ->andReturn($newSubscription);

        $this->app->instance(ChangeSubscriptionPlanAction::class, $mockAction);

        Event::fake();

        $result = $this->user->changePlan($this->newPlan->id, true); // Explicit true should override config

        $this->assertTrue($result);

        Event::assertDispatched(SubscriptionChanged::class, function ($event) {
            return $event->changeSummary['reset_usage'] === true; // Should use explicit value
        });
    }

    /** @test */
    public function it_fails_to_change_plan_when_no_active_subscription(): void
    {
        $result = $this->user->changePlan($this->newPlan->id);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_fails_to_change_plan_when_change_not_allowed(): void
    {
        // Create trial subscription and disable plan changes during trial
        config(['sub-sphere.plan_changes.allow_plan_change_during_trial' => false]);

        $this->user->subscriptions()->create([
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::TRIAL,
            'starts_at' => now(),
            'trial_ends_at' => now()->addDays(7),
            'ends_at' => now()->addDays(30),
        ]);

        $result = $this->user->changePlan($this->newPlan->id);

        $this->assertFalse($result);
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

        // Mock the CancelSubscriptionAction execution
        $mockAction = Mockery::mock('overload:' . CancelSubscriptionAction::class);
        $mockAction->shouldReceive('execute')
            ->once()
            ->andReturn($subscription);

        $result = $this->user->cancelSubscription();

        $this->assertTrue($result);
    }

    /** @test */
    public function it_fails_to_cancel_subscription_when_no_active_subscription(): void
    {
        $result = $this->user->cancelSubscription();

        $this->assertFalse($result);
    }

    /** @test */
    public function it_can_resume_subscription(): void
    {
        // Create canceled subscription that's still within paid period
        $subscription = $this->user->subscriptions()->create([
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::CANCELED,
            'starts_at' => now()->subDays(15),
            'ends_at' => now()->addDays(15), // Still valid for 15 more days
            'updated_at' => now(),
        ]);

        // Mock the ResumeSubscriptionAction execution
        $mockAction = Mockery::mock('overload:' . ResumeSubscriptionAction::class);
        $mockAction->shouldReceive('execute')
            ->once()
            ->andReturn($subscription);

        $result = $this->user->resumeSubscription();

        $this->assertTrue($result);
    }

    /** @test */
    public function it_fails_to_resume_subscription_when_no_canceled_subscription(): void
    {
        $result = $this->user->resumeSubscription();

        $this->assertFalse($result);
    }

    /** @test */
    public function it_fails_to_resume_subscription_when_subscription_period_expired(): void
    {
        // Create canceled subscription that's expired
        $this->user->subscriptions()->create([
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::CANCELED,
            'starts_at' => now()->subDays(45),
            'ends_at' => now()->subDays(15), // Expired 15 days ago
            'updated_at' => now(),
        ]);

        $result = $this->user->resumeSubscription();

        $this->assertFalse($result);
    }

    /** @test */
    public function it_can_check_if_subscribed_to_specific_plan(): void
    {
        // Create active subscription
        $this->user->subscriptions()->create([
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        $this->assertTrue($this->user->isSubscribedTo($this->plan->id));
        $this->assertFalse($this->user->isSubscribedTo($this->newPlan->id));
    }

    /** @test */
    public function it_returns_false_when_not_subscribed_to_specific_plan(): void
    {
        // No subscription
        $this->assertFalse($this->user->isSubscribedTo($this->plan->id));

        // Create inactive subscription
        $this->user->subscriptions()->create([
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::EXPIRED,
            'starts_at' => now()->subDays(60),
            'ends_at' => now()->subDays(30),
        ]);

        $this->assertFalse($this->user->isSubscribedTo($this->plan->id));
    }

    /** @test */
    public function it_can_get_current_subscription_status(): void
    {
        // No subscription
        $this->assertNull($this->user->subscriptionStatus());

        // Create active subscription
        $this->user->subscriptions()->create([
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        $this->assertEquals(SubscriptionStatus::ACTIVE, $this->user->subscriptionStatus());
    }

    /** @test */
    public function it_can_get_trial_subscription_status(): void
    {
        // Create trial subscription
        $this->user->subscriptions()->create([
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::TRIAL,
            'starts_at' => now(),
            'trial_ends_at' => now()->addDays(7),
            'ends_at' => now()->addDays(30),
        ]);

        $this->assertEquals(SubscriptionStatus::TRIAL, $this->user->subscriptionStatus());
    }

    /** @test */
    public function it_returns_null_status_when_no_active_subscription(): void
    {
        // Create expired subscription
        $this->user->subscriptions()->create([
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::EXPIRED,
            'starts_at' => now()->subDays(60),
            'ends_at' => now()->subDays(30),
        ]);

        $this->assertNull($this->user->subscriptionStatus());
    }

    /** @test */
    public function it_handles_exceptions_gracefully_in_change_plan(): void
    {
        // Create active subscription with proper relationships
        $subscription = $this->user->subscriptions()->create([
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        // Load the relationships properly
        $subscription->load(['plan', 'planPricing']);

        // Mock the ChangeSubscriptionPlanAction to throw exception
        $mockAction = Mockery::mock(ChangeSubscriptionPlanAction::class);
        $mockAction->shouldReceive('execute')
            ->once()
            ->andThrow(new \Exception('Plan change failed'));

        $this->app->instance(ChangeSubscriptionPlanAction::class, $mockAction);

        $result = $this->user->changePlan($this->newPlan->id);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_handles_exceptions_gracefully_in_cancel_subscription(): void
    {
        // Create active subscription
        $subscription = $this->user->subscriptions()->create([
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        // Mock the CancelSubscriptionAction to throw exception
        $mockAction = Mockery::mock('overload:' . CancelSubscriptionAction::class);
        $mockAction->shouldReceive('execute')
            ->once()
            ->andThrow(new \Exception('Cancellation failed'));

        $result = $this->user->cancelSubscription();

        $this->assertFalse($result);
    }

    /** @test */
    public function it_handles_exceptions_gracefully_in_resume_subscription(): void
    {
        // Create canceled subscription
        $subscription = $this->user->subscriptions()->create([
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => SubscriptionStatus::CANCELED,
            'starts_at' => now()->subDays(15),
            'ends_at' => now()->addDays(15),
            'updated_at' => now(),
        ]);

        // Mock the ResumeSubscriptionAction to throw exception
        $mockAction = Mockery::mock('overload:' . ResumeSubscriptionAction::class);
        $mockAction->shouldReceive('execute')
            ->once()
            ->andThrow(new \Exception('Resume failed'));

        $result = $this->user->resumeSubscription();

        $this->assertFalse($result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
