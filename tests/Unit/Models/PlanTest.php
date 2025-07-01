<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Tests\Unit\Models;

use AhmedEssam\SubSphere\Models\Plan;
use AhmedEssam\SubSphere\Models\PlanFeature;
use AhmedEssam\SubSphere\Models\PlanPricing;
use AhmedEssam\SubSphere\Models\Subscription;
use AhmedEssam\SubSphere\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PlanTest extends TestCase
{
    use RefreshDatabase;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plan = $this->createTestPlan();
    }

    /** @test */
    public function it_can_be_created_with_valid_attributes(): void
    {
        $plan = $this->createTestPlan([
            'slug' => 'premium-plan',
            'name' => ['en' => 'Premium Plan'],
            'description' => ['en' => 'Premium subscription plan'],
            'is_active' => true,
        ]);

        $this->assertInstanceOf(Plan::class, $plan);
        $this->assertEquals('Premium Plan', $plan->getTranslation('name', 'en'));
        $this->assertEquals('Premium subscription plan', $plan->getTranslation('description', 'en'));
        $this->assertTrue($plan->is_active);
        $this->assertFalse($plan->trashed());
    }

    /** @test */
    public function it_can_be_soft_deleted(): void
    {
        $this->plan->delete();

        $this->assertTrue($this->plan->fresh()->trashed());
        $this->assertNotNull($this->plan->fresh()->deleted_at);
    }

    /** @test */
    public function it_has_pricings_relationship(): void
    {
        $pricing = PlanPricing::create([
            'plan_id' => $this->plan->id,
            'label' => 'Monthly',
            'duration_in_days' => 30,
            'price' => 99.99,
            'is_best_offer' => false,
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $this->plan->pricings);
        $this->assertTrue($this->plan->pricings->contains($pricing));
    }

    /** @test */
    public function it_has_features_relationship(): void
    {
        $feature = PlanFeature::create([
            'plan_id' => $this->plan->id,
            'key' => 'api_calls',
            'value' => '1000',
            'reset_period' => 'monthly',
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $this->plan->features);
        $this->assertTrue($this->plan->features->contains($feature));
    }

    /** @test */
    public function it_has_subscriptions_relationship(): void
    {
        $user = \AhmedEssam\SubSphere\Tests\Models\User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $pricing = PlanPricing::create([
            'plan_id' => $this->plan->id,
            'label' => 'Monthly',
            'duration_in_days' => 30,
            'price' => 99.99,
            'is_best_offer' => false,
        ]);

        $subscription = Subscription::create([
            'subscriber_type' => get_class($user),
            'subscriber_id' => $user->id,
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $pricing->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $this->plan->subscriptions);
        $this->assertTrue($this->plan->subscriptions->contains($subscription));
    }

    /** @test */
    public function it_casts_attributes_correctly(): void
    {
        $plan = $this->createTestPlan([
            'slug' => 'cast-test-plan',
            'name' => ['en' => 'Test Plan'],
            'description' => ['en' => 'Test Description'],
            'is_active' => '1', // String that should be cast to boolean
        ]);

        $this->assertIsBool($plan->is_active);
        $this->assertTrue($plan->is_active);
        // Test that translatable fields work correctly
        $this->assertEquals('Test Plan', $plan->getTranslation('name', 'en'));
        $this->assertEquals('Test Description', $plan->getTranslation('description', 'en'));
    }

    /** @test */
    public function it_handles_translatable_fields_for_name_and_description(): void
    {
        $plan = $this->createTestPlan([
            'slug' => 'json-test-plan',
            'name' => ['en' => 'English Name', 'ar' => 'اسم عربي'],
            'description' => ['en' => 'English Description', 'ar' => 'وصف عربي'],
            'is_active' => true,
        ]);

        // Test Spatie's translatable methods
        $this->assertEquals('English Name', $plan->getTranslation('name', 'en'));
        $this->assertEquals('اسم عربي', $plan->getTranslation('name', 'ar'));
        $this->assertEquals('English Description', $plan->getTranslation('description', 'en'));
        $this->assertEquals('وصف عربي', $plan->getTranslation('description', 'ar'));

        // Test our helper methods
        $this->assertEquals('English Name', $plan->getLocalizedName('en'));
        $this->assertEquals('اسم عربي', $plan->getLocalizedName('ar'));
        $this->assertEquals('English Description', $plan->getLocalizedDescription('en'));
        $this->assertEquals('وصف عربي', $plan->getLocalizedDescription('ar'));

        // Test backward compatibility methods
        $translations = $plan->getTranslationsArray('name');
        $this->assertEquals('English Name', $translations['en']);
        $this->assertEquals('اسم عربي', $translations['ar']);
    }

    /** @test */
    public function it_can_have_multiple_pricings(): void
    {
        $monthlyPricing = PlanPricing::create([
            'plan_id' => $this->plan->id,
            'label' => ['en' => 'Monthly', 'ar' => 'شهري'],
            'duration_in_days' => 30,
            'price' => 99.99,
            'is_best_offer' => false,
        ]);

        $yearlyPricing = PlanPricing::create([
            'plan_id' => $this->plan->id,
            'label' => ['en' => 'Yearly', 'ar' => 'سنوي'],
            'duration_in_days' => 365,
            'price' => 999.99,
            'is_best_offer' => true,
        ]);

        $this->assertCount(2, $this->plan->pricings);
        $this->assertTrue($this->plan->pricings->contains($monthlyPricing));
        $this->assertTrue($this->plan->pricings->contains($yearlyPricing));
    }

    /** @test */
    public function it_can_have_multiple_features(): void
    {
        $apiCallsFeature = PlanFeature::create([
            'plan_id' => $this->plan->id,
            'key' => 'api_calls',
            'value' => '1000',
            'reset_period' => 'monthly',
        ]);

        $storageFeature = PlanFeature::create([
            'plan_id' => $this->plan->id,
            'key' => 'storage_gb',
            'value' => '100',
            'reset_period' => 'never',
        ]);

        $this->assertCount(2, $this->plan->features);
        $this->assertTrue($this->plan->features->contains($apiCallsFeature));
        $this->assertTrue($this->plan->features->contains($storageFeature));
    }
}
