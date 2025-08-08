<?php

namespace AhmedEssam\SubSphere\Tests\Feature;

use AhmedEssam\SubSphere\Actions\DuplicateSubscriptionAction;
use AhmedEssam\SubSphere\Enums\SubscriptionStatus;
use AhmedEssam\SubSphere\Events\SubscriptionCreated;
use AhmedEssam\SubSphere\Events\SubscriptionStarted;
use AhmedEssam\SubSphere\Events\TrialStarted;
use AhmedEssam\SubSphere\Exceptions\CouldNotStartSubscriptionException;
use AhmedEssam\SubSphere\Exceptions\SubscriptionException;
use AhmedEssam\SubSphere\Models\Plan;
use AhmedEssam\SubSphere\Models\PlanFeature;
use AhmedEssam\SubSphere\Models\PlanPricing;
use AhmedEssam\SubSphere\Models\Subscription;
use AhmedEssam\SubSphere\Models\SubscriptionUsage;
use AhmedEssam\SubSphere\Tests\TestCase;
use AhmedEssam\SubSphere\Tests\TestDataHelper;
use AhmedEssam\SubSphere\Tests\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

class DuplicateSubscriptionActionTest extends TestCase
{
    use RefreshDatabase, TestDataHelper;

    private User $user;
    private Plan $plan;
    private PlanPricing $pricing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->createTestUser();

        $this->plan = $this->createTestPlan([
            'name' => ['en' => 'Test Plan'],
            'is_active' => true,
            'trial_days' => 7,
        ]);

        $this->pricing = $this->createTestPlanPricing($this->plan, [
            'price' => 9.99,
            'currency' => 'USD',
            'duration_value' => 30,
            'duration_unit' => 'day',
        ]);

        // Create some plan features
        $this->createTestPlanFeature($this->plan, [
            'key' => 'downloads',
            'name' => ['en' => 'Downloads'],
            'value' => ['en' => 100],
            'reset_period' => 'monthly',
        ]);

        $this->createTestPlanFeature($this->plan, [
            'key' => 'storage',
            'name' => ['en' => 'Storage'],
            'value' => ['en' => 1024],
            'reset_period' => 'never',
        ]);
    }

    /** @test */
    public function it_can_duplicate_expired_subscription()
    {
        Event::fake([
            SubscriptionCreated::class,
            SubscriptionStarted::class,
        ]);

        // Create an expired subscription with usage
        $originalSubscription = $this->createTestSubscription($this->user, $this->plan, $this->pricing, [
            'status' => SubscriptionStatus::EXPIRED,
            'starts_at' => now()->subDays(60),
            'ends_at' => now()->subDays(30),
            'trial_ends_at' => null,
            'canceled_at' => null,
            'resumed_at' => null,
            'renewed_at' => null,
        ]);

        // Add some usage records
        SubscriptionUsage::create([
            'subscription_id' => $originalSubscription->id,
            'key' => 'downloads',
            'used' => 50,
            'valid_until' => now()->subDays(30),
        ]);

        SubscriptionUsage::create([
            'subscription_id' => $originalSubscription->id,
            'key' => 'storage',
            'used' => 512,
            'valid_until' => null,
        ]);

        $action = new DuplicateSubscriptionAction([
            'subscriber' => $this->user,
            'subscription_id' => $originalSubscription->id,
        ]);

        $newSubscription = $action->execute();

        // Verify new subscription properties
        $this->assertInstanceOf(Subscription::class, $newSubscription);
        $this->assertEquals($this->user->id, $newSubscription->subscriber_id);
        $this->assertEquals(User::class, $newSubscription->subscriber_type);
        $this->assertEquals($this->plan->id, $newSubscription->plan_id);
        $this->assertEquals($this->pricing->id, $newSubscription->plan_pricing_id);
        $this->assertEquals(SubscriptionStatus::ACTIVE, $newSubscription->status);

        // Verify dates are reset
        $this->assertTrue($newSubscription->starts_at->isToday());
        $this->assertEquals(
            now()->addDays(30)->toDateString(),
            $newSubscription->ends_at->toDateString()
        );

        // Verify usage records are duplicated with reset values
        $newUsages = $newSubscription->usages;
        $this->assertCount(2, $newUsages);

        $downloadsUsage = $newUsages->firstWhere('key', 'downloads');
        $this->assertEquals(0, $downloadsUsage->used);

        $storageUsage = $newUsages->firstWhere('key', 'storage');
        $this->assertEquals(0, $storageUsage->used);
        $this->assertNull($storageUsage->valid_until);

        // Verify events were dispatched
        Event::assertDispatched(SubscriptionCreated::class, function ($event) use ($newSubscription, $originalSubscription) {
            return $event->subscriber->is($this->user) &&
                $event->subscription->is($newSubscription) &&
                $event->plan->is($this->plan) &&
                $event->details['action'] === 'duplicate' &&
                $event->details['original_subscription_id'] === $originalSubscription->id;
        });

        Event::assertDispatched(SubscriptionStarted::class, function ($event) use ($newSubscription) {
            return $event->subscription->is($newSubscription);
        });
    }

    /** @test */
    public function it_can_duplicate_canceled_subscription()
    {
        Event::fake();

        $originalSubscription = $this->createTestSubscription($this->user, $this->plan, $this->pricing, [
            'status' => SubscriptionStatus::CANCELED,
            'starts_at' => now()->subDays(30),
            'ends_at' => now()->addDays(10),
            'canceled_at' => now()->subDays(5),
        ]);

        $action = new DuplicateSubscriptionAction([
            'subscriber' => $this->user,
            'subscription_id' => $originalSubscription->id,
        ]);

        $newSubscription = $action->execute();

        $this->assertEquals(SubscriptionStatus::ACTIVE, $newSubscription->status);
        $this->assertNull($newSubscription->canceled_at);
    }

    /** @test */
    public function it_can_duplicate_subscription_with_trial()
    {
        Event::fake([
            SubscriptionCreated::class,
            TrialStarted::class,
        ]);

        $originalSubscription = $this->createTestSubscription($this->user, $this->plan, $this->pricing, [
            'status' => SubscriptionStatus::EXPIRED,
            'started_at' => now()->subDays(60),
            'ends_at' => now()->subDays(30),
            'trial_ends_at' => now()->subDays(53),
        ]);

        $action = new DuplicateSubscriptionAction([
            'subscriber' => $this->user,
            'subscription_id' => $originalSubscription->id,
            'with_trial' => true,
        ]);

        $newSubscription = $action->execute();

        // Check that trial uses config value, not plan field
        $trialDays = config('sub-sphere.trial_period_days', 14);
        $this->assertGreaterThan(0, $trialDays, 'Config should have trial days');

        // Verify trial dates are reset
        $this->assertNotNull($newSubscription->trial_ends_at, 'Trial end date should be set when with_trial is true');
        $this->assertTrue($newSubscription->trial_ends_at->isToday() || $newSubscription->trial_ends_at->isFuture());

        // Verify the trial end date is correct based on config
        $expectedTrialEndDate = now()->addDays($trialDays);
        $this->assertEquals(
            $expectedTrialEndDate->format('Y-m-d H:i'),
            $newSubscription->trial_ends_at->format('Y-m-d H:i')
        );

        Event::assertDispatched(TrialStarted::class, function ($event) use ($newSubscription) {
            return $event->subscription->is($newSubscription);
        });
    }

    /** @test */
    public function it_cannot_duplicate_active_subscription_when_user_has_active_subscription()
    {
        // Create an active subscription for the user
        $this->createTestSubscription($this->user, $this->plan, $this->pricing, [
            'status' => SubscriptionStatus::ACTIVE,
            'started_at' => now()->subDays(5),
            'ends_at' => now()->addDays(25),
        ]);

        // Create an expired subscription to duplicate
        $expiredSubscription = $this->createTestSubscription($this->user, $this->plan, $this->pricing, [
            'status' => SubscriptionStatus::EXPIRED,
            'started_at' => now()->subDays(60),
            'ends_at' => now()->subDays(30),
        ]);

        $action = new DuplicateSubscriptionAction([
            'subscriber' => $this->user,
            'subscription_id' => $expiredSubscription->id,
        ]);

        $this->expectException(CouldNotStartSubscriptionException::class);

        $action->execute();
    }

    /** @test */
    public function it_cannot_duplicate_currently_active_subscription()
    {
        // Create a separate user without any existing subscriptions
        $testUser = $this->createTestUser([
            'email' => 'testuser2@example.com',
        ]);

        $activeSubscription = $this->createTestSubscription($testUser, $this->plan, $this->pricing, [
            'status' => SubscriptionStatus::ACTIVE,
            'started_at' => now()->subDays(5),
            'ends_at' => now()->addDays(25),
        ]);

        $action = new DuplicateSubscriptionAction([
            'subscriber' => $testUser,
            'subscription_id' => $activeSubscription->id,
        ]);

        $this->expectException(SubscriptionException::class);
        $this->expectExceptionMessage('Cannot duplicate an active subscription');

        $action->execute();
    }

    /** @test */
    public function it_cannot_duplicate_nonexistent_subscription()
    {
        $action = new DuplicateSubscriptionAction([
            'subscriber' => $this->user,
            'subscription_id' => 99999,
        ]);

        $this->expectException(SubscriptionException::class);
        $this->expectExceptionMessage('Subscription not found');

        $action->execute();
    }

    /** @test */
    public function it_cannot_duplicate_subscription_of_different_user()
    {
        $otherUser = $this->createTestUser([
            'name' => 'Other User',
            'email' => 'other@example.com',
        ]);

        $otherSubscription = $this->createTestSubscription($otherUser, $this->plan, $this->pricing, [
            'status' => SubscriptionStatus::EXPIRED,
        ]);

        $action = new DuplicateSubscriptionAction([
            'subscriber' => $this->user,
            'subscription_id' => $otherSubscription->id,
        ]);

        $this->expectException(SubscriptionException::class);
        $this->expectExceptionMessage('Subscription does not belong to this subscriber');

        $action->execute();
    }

    /** @test */
    public function it_cannot_duplicate_subscription_with_inactive_plan()
    {
        // Make the plan inactive
        $this->plan->update(['is_active' => false]);

        $expiredSubscription = $this->createTestSubscription($this->user, $this->plan, $this->pricing, [
            'status' => SubscriptionStatus::EXPIRED,
        ]);

        $action = new DuplicateSubscriptionAction([
            'subscriber' => $this->user,
            'subscription_id' => $expiredSubscription->id,
        ]);

        $this->expectException(SubscriptionException::class);
        $this->expectExceptionMessage('Cannot duplicate subscription with inactive plan');

        $action->execute();
    }

    /** @test */
    public function it_can_use_can_duplicate_validation_method()
    {
        // Test with no subscription - should return false
        $this->assertFalse($this->user->canDuplicateSubscription());

        // Create expired subscription
        $expiredSubscription = $this->createTestSubscription($this->user, $this->plan, $this->pricing, [
            'status' => SubscriptionStatus::EXPIRED,
        ]);

        // Should be able to duplicate expired subscription
        $this->assertTrue($this->user->canDuplicateSubscription($expiredSubscription->id));

        // Create active subscription - should prevent duplication
        $this->createTestSubscription($this->user, $this->plan, $this->pricing, [
            'status' => SubscriptionStatus::ACTIVE,
            'started_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        // Should not be able to duplicate when already has active subscription
        $this->assertFalse($this->user->canDuplicateSubscription($expiredSubscription->id));
    }

    /** @test */
    public function it_duplicates_subscription_with_custom_start_date()
    {
        $customStartDate = now()->addDays(5);

        $originalSubscription = $this->createTestSubscription($this->user, $this->plan, $this->pricing, [
            'status' => SubscriptionStatus::EXPIRED,
        ]);

        $action = new DuplicateSubscriptionAction([
            'subscriber' => $this->user,
            'subscription_id' => $originalSubscription->id,
            'start_date' => $customStartDate,
        ]);

        $newSubscription = $action->execute();

        $this->assertEquals($customStartDate->toDateString(), $newSubscription->starts_at->toDateString());
        $this->assertNotNull($newSubscription->ends_at);
        $expectedEndDate = $customStartDate->copy()->addDays(30);
        $this->assertEquals($expectedEndDate->toDateString(), $newSubscription->ends_at->toDateString());
    }
}
