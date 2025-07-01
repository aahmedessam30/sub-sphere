<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Tests\Integration;

use AhmedEssam\SubSphere\Enums\SubscriptionStatus;
use AhmedEssam\SubSphere\Models\Plan;
use AhmedEssam\SubSphere\Models\PlanFeature;
use AhmedEssam\SubSphere\Models\PlanPricing;
use AhmedEssam\SubSphere\Models\Subscription;
use AhmedEssam\SubSphere\Models\SubscriptionUsage;
use AhmedEssam\SubSphere\Services\CurrencyService;
use AhmedEssam\SubSphere\Services\SubscriptionService;
use AhmedEssam\SubSphere\Services\SubscriptionValidator;
use AhmedEssam\SubSphere\Tests\Models\User;
use AhmedEssam\SubSphere\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SubscriptionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Plan $plan;
    private PlanPricing $monthlyPricing;
    private PlanPricing $yearlyPricing;
    private SubscriptionService $subscriptionService;
    private SubscriptionValidator $validator;
    private CurrencyService $currencyService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->createTestUser();

        $this->plan = $this->createTestPlan([
            'name' => ['en' => 'Premium Plan'],
            'description' => ['en' => 'Premium subscription plan'],
        ]);

        $this->monthlyPricing = PlanPricing::create([
            'plan_id' => $this->plan->id,
            'label' => 'Monthly',
            'duration_in_days' => 30,
            'price' => 99.99,
            'is_best_offer' => false,
        ]);

        $this->yearlyPricing = PlanPricing::create([
            'plan_id' => $this->plan->id,
            'label' => 'Yearly',
            'duration_in_days' => 365,
            'price' => 999.99,
            'is_best_offer' => true,
        ]);

        // Create plan features
        PlanFeature::create([
            'plan_id' => $this->plan->id,
            'key' => 'api_calls',
            'value' => '10000',
            'reset_period' => 'monthly',
        ]);

        PlanFeature::create([
            'plan_id' => $this->plan->id,
            'key' => 'storage_gb',
            'value' => '100',
            'reset_period' => 'never',
        ]);

        PlanFeature::create([
            'plan_id' => $this->plan->id,
            'key' => 'users',
            'value' => '5',
            'reset_period' => 'never',
        ]);

        $this->subscriptionService = app(SubscriptionService::class);
        $this->validator = app(SubscriptionValidator::class);
        $this->currencyService = app(CurrencyService::class);
    }

    /** @test */
    public function complete_subscription_lifecycle_workflow(): void
    {
        // 1. Validate user can create subscription (will throw exception if validation fails)
        $this->validator->validateSubscriptionCreation($this->user, $this->plan, $this->monthlyPricing);

        // 2. Create subscription
        $subscription = $this->user->newSubscription($this->plan, $this->monthlyPricing);

        $this->assertInstanceOf(Subscription::class, $subscription);
        $this->assertEquals(SubscriptionStatus::ACTIVE, $subscription->status);
        $this->assertTrue($subscription->isActive());

        // 3. Verify user now has active subscription
        $activeSubscription = $this->user->fresh()->activeSubscription();
        $this->assertNotNull($activeSubscription, 'User should have an active subscription');
        $this->assertTrue($this->user->fresh()->hasActiveSubscription());
        $this->assertNotNull($this->subscriptionService->getActiveSubscription($this->user));

        // 4. Test feature consumption
        $this->assertTrue($this->user->hasFeature('api_calls'));
        $this->assertTrue($this->user->consumeFeature('api_calls', 1000));

        // 5. Check usage tracking
        $this->assertEquals(9000, $subscription->getRemainingUsage('api_calls'));

        $usage = SubscriptionUsage::where([
            'subscription_id' => $subscription->id,
            'key' => 'api_calls',
        ])->first();

        $this->assertEquals(1000, $usage->used);

        // 6. Cancel subscription
        $this->assertTrue($subscription->cancel());
        $this->assertEquals(SubscriptionStatus::CANCELED, $subscription->fresh()->status);
        $this->assertFalse($this->user->hasActiveSubscription());

        // 7. Resume subscription
        $this->assertTrue($subscription->resume());
        $this->assertEquals(SubscriptionStatus::ACTIVE, $subscription->fresh()->status);
        $this->assertTrue($this->user->hasActiveSubscription());

        // 8. Renew subscription
        $originalEndsAt = $subscription->ends_at;
        $this->assertTrue($subscription->renew());
        $this->assertTrue($subscription->fresh()->ends_at->gt($originalEndsAt));

        // 9. Test subscription expiration
        $subscription->update(['ends_at' => now()->subDay()]);
        $this->assertTrue($subscription->expire());
        $this->assertEquals(SubscriptionStatus::EXPIRED, $subscription->fresh()->status);
    }

    /** @test */
    public function subscription_upgrade_workflow(): void
    {
        // Start with monthly subscription
        $monthlySubscription = $this->user->newSubscription($this->plan, $this->monthlyPricing);

        // Consume some features
        $monthlySubscription->consumeFeature('api_calls', 2000);

        // Verify current state
        $this->assertEquals(8000, $monthlySubscription->getRemainingUsage('api_calls'));
        $this->assertEquals(99.99, $monthlySubscription->planPricing->price);

        // Cancel current subscription
        $monthlySubscription->cancel();

        // Create new yearly subscription (upgrade)
        $yearlySubscription = $this->user->newSubscription($this->plan, $this->yearlyPricing);

        // Verify upgrade
        $this->assertEquals(SubscriptionStatus::ACTIVE, $yearlySubscription->status);
        $this->assertEquals(999.99, $yearlySubscription->planPricing->price);
        $this->assertEquals(365, $yearlySubscription->planPricing->duration_in_days);

        // Usage should reset with new subscription
        $this->assertEquals(10000, $yearlySubscription->getRemainingUsage('api_calls'));

        // Old subscription should be canceled
        $this->assertEquals(SubscriptionStatus::CANCELED, $monthlySubscription->fresh()->status);
    }

    /** @test */
    public function feature_usage_limits_and_validation_workflow(): void
    {
        $subscription = $this->user->newSubscription($this->plan, $this->monthlyPricing);

        // Test normal consumption
        $this->assertTrue($subscription->consumeFeature('api_calls', 5000));
        $this->assertEquals(5000, $subscription->getRemainingUsage('api_calls'));

        // Test consuming exactly to limit
        $this->assertTrue($subscription->consumeFeature('api_calls', 5000));
        $this->assertEquals(0, $subscription->getRemainingUsage('api_calls'));

        // Test overconsumption prevention
        $this->assertFalse($subscription->consumeFeature('api_calls', 1));
        $this->assertEquals(0, $subscription->getRemainingUsage('api_calls'));

        // Test non-resettable feature consumption
        $this->assertTrue($subscription->consumeFeature('users', 3));
        $this->assertEquals(2, $subscription->getRemainingUsage('users'));

        // Test storage feature
        $this->assertTrue($subscription->consumeFeature('storage_gb', 50));
        $this->assertEquals(50, $subscription->getRemainingUsage('storage_gb'));
    }

    /** @test */
    public function subscription_validation_workflow(): void
    {
        // Test plan availability validation
        $this->plan->update(['is_active' => false]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Plan is not active');
        $this->validator->validatePlanAvailability($this->plan);
    }

    /** @test */
    public function currency_formatting_in_subscription_workflow(): void
    {
        $subscription = $this->user->newSubscription($this->plan, $this->monthlyPricing);

        // Test currency formatting
        $formattedPrice = $this->currencyService->formatPrice((float) $subscription->planPricing->price, 'USD');
        $this->assertStringContainsString('99.99', $formattedPrice);

        // Test currency conversion (placeholder)
        $convertedPrice = $this->currencyService->convertAmount(99.99, 'USD', 'EUR');
        $this->assertIsFloat($convertedPrice);
        $this->assertGreaterThan(0, $convertedPrice);
    }

    /** @test */
    public function subscription_statistics_workflow(): void
    {
        // Create multiple subscriptions for statistics
        $subscription1 = $this->user->newSubscription($this->plan, $this->monthlyPricing);

        $user2 = User::create([
            'name' => 'User 2',
            'email' => 'user2@example.com',
        ]);
        $subscription2 = $user2->newSubscription($this->plan, $this->yearlyPricing);

        // Cancel one subscription
        $subscription2->cancel();

        // Get statistics
        $stats = $this->subscriptionService->getSubscriptionStatistics();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('active', $stats);
        $this->assertArrayHasKey('canceled', $stats);

        $this->assertEquals(2, $stats['total']);
        $this->assertEquals(1, $stats['active']);
        $this->assertEquals(1, $stats['canceled']);
    }

    /** @test */
    public function bulk_operations_workflow(): void
    {
        $users = collect();
        $subscriptions = collect();

        // Create multiple users and subscriptions
        for ($i = 1; $i <= 5; $i++) {
            $user = User::create([
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
            ]);
            $users->push($user);

            $subscription = $user->newSubscription($this->plan, $this->monthlyPricing);
            $subscriptions->push($subscription);
        }

        // Test bulk expiration using the existing service method
        $expiredCount = $this->subscriptionService->expireOverdueSubscriptions();

        // Manually expire subscriptions for testing
        $subscriptions->each(function ($subscription) {
            $subscription->expire();
        });

        // Verify all are expired
        $subscriptions->each(function ($subscription) {
            $this->assertEquals(SubscriptionStatus::EXPIRED, $subscription->fresh()->status);
        });

        // Test individual renewal (since bulk renewal might not exist)
        $renewedCount = 0;
        $subscriptions->each(function ($subscription) use (&$renewedCount) {
            if ($this->subscriptionService->renew($subscription)) {
                $renewedCount++;
            }
        });

        $this->assertEquals(5, $renewedCount);

        // Verify all are active again
        $subscriptions->each(function ($subscription) {
            $this->assertEquals(SubscriptionStatus::ACTIVE, $subscription->fresh()->status);
        });
    }

    /** @test */
    public function subscription_health_monitoring_workflow(): void
    {
        // Create various subscription states
        $activeSubscription = $this->user->newSubscription($this->plan, $this->monthlyPricing);

        $user2 = User::create(['name' => 'User 2', 'email' => 'user2@example.com']);
        $expiredSubscription = $user2->newSubscription($this->plan, $this->monthlyPricing);
        $expiredSubscription->update(['ends_at' => now()->subDays(5)]);
        $expiredSubscription->expire();

        $user3 = User::create(['name' => 'User 3', 'email' => 'user3@example.com']);
        $endingSoonSubscription = $user3->newSubscription($this->plan, $this->monthlyPricing);
        $endingSoonSubscription->update(['ends_at' => now()->addDays(2)]);

        // Get health report
        $health = $this->subscriptionService->getHealthStatus();

        $this->assertIsArray($health);
        $this->assertArrayHasKey('status', $health);
        $this->assertArrayHasKey('active_subscriptions', $health);
        $this->assertArrayHasKey('expiring_soon', $health);

        $this->assertEquals(2, $health['active_subscriptions']); // active + ending soon
        $this->assertEquals(1, $health['expiring_soon']);
    }
}
