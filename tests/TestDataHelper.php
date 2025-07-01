<?php

namespace AhmedEssam\SubSphere\Tests;

use AhmedEssam\SubSphere\Models\Plan;
use AhmedEssam\SubSphere\Models\PlanFeature;
use AhmedEssam\SubSphere\Models\PlanPricing;
use AhmedEssam\SubSphere\Models\Subscription;
use AhmedEssam\SubSphere\Models\SubscriptionUsage;
use AhmedEssam\SubSphere\Tests\Models\User;
use Illuminate\Support\Str;

trait TestDataHelper
{
    /**
     * Create a test plan with all required fields.
     */
    protected function createTestPlan(array $attributes = []): Plan
    {
        $defaults = [
            'slug' => 'test-plan-' . Str::random(8),
            'name' => ['en' => 'Test Plan'],
            'description' => ['en' => 'A test plan'],
            'is_active' => true,
        ];

        return Plan::create(array_merge($defaults, $attributes));
    }

    /**
     * Create a test user.
     */
    protected function createTestUser(array $attributes = []): User
    {
        $defaults = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
        ];

        return User::create(array_merge($defaults, $attributes));
    }

    /**
     * Create a test plan pricing.
     */
    protected function createTestPlanPricing(Plan $plan, array $attributes = []): PlanPricing
    {
        $defaults = [
            'plan_id' => $plan->id,
            'label' => ['en' => 'Monthly'],
            'duration_in_days' => 30,
            'price' => 9.99,
            'is_best_offer' => false,
        ];

        return PlanPricing::create(array_merge($defaults, $attributes));
    }

    /**
     * Create a test plan feature.
     */
    protected function createTestPlanFeature(Plan $plan, array $attributes = []): PlanFeature
    {
        $defaults = [
            'plan_id' => $plan->id,
            'key' => 'test-feature-' . Str::random(8),
            'name' => ['en' => 'Test Feature'],
            'description' => ['en' => 'A test feature'],
            'value' => '10',
            'reset_period' => 'monthly',
        ];

        return PlanFeature::create(array_merge($defaults, $attributes));
    }

    /**
     * Create a test subscription.
     */
    protected function createTestSubscription(User $user, Plan $plan, PlanPricing $pricing, array $attributes = []): Subscription
    {
        $defaults = [
            'subscriber_id' => $user->id,
            'subscriber_type' => User::class,
            'plan_id' => $plan->id,
            'plan_pricing_id' => $pricing->id,
            'slug' => 'test-subscription-' . Str::random(8),
            'name' => ['en' => 'Test Subscription'],
            'description' => ['en' => 'A test subscription'],
            'trial_ends_at' => null,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'cancels_at' => null,
            'canceled_at' => null,
        ];

        return Subscription::create(array_merge($defaults, $attributes));
    }

    /**
     * Create a test subscription usage.
     */
    protected function createTestSubscriptionUsage(Subscription $subscription, string $key, array $attributes = []): SubscriptionUsage
    {
        $defaults = [
            'subscription_id' => $subscription->id,
            'key' => $key,
            'used' => 1,
            'last_used_at' => now(),
        ];

        return SubscriptionUsage::create(array_merge($defaults, $attributes));
    }
}
