<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Tests\Unit\Models;

use AhmedEssam\SubSphere\Models\Plan;
use AhmedEssam\SubSphere\Models\PlanPricing;
use AhmedEssam\SubSphere\Models\Subscription;
use AhmedEssam\SubSphere\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PlanPricingTest extends TestCase
{
    use RefreshDatabase;

    private Plan $plan;
    private PlanPricing $pricing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plan = $this->createTestPlan();

        $this->pricing = PlanPricing::create([
            'plan_id' => $this->plan->id,
            'label' => 'Monthly',
            'duration_in_days' => 30,
            'price' => 99.99,
            'is_best_offer' => false,
        ]);
    }

    /** @test */
    public function it_can_be_created_with_valid_attributes(): void
    {
        $pricing = PlanPricing::create([
            'plan_id' => $this->plan->id,
            'label' => 'Yearly',
            'duration_in_days' => 365,
            'price' => 999.99,
            'is_best_offer' => true,
        ]);

        $this->assertInstanceOf(PlanPricing::class, $pricing);
        $this->assertEquals('Yearly', $pricing->label);
        $this->assertEquals(365, $pricing->duration_in_days);
        $this->assertEquals(999.99, $pricing->price);
        $this->assertTrue($pricing->is_best_offer);
        $this->assertEquals($this->plan->id, $pricing->plan_id);
    }

    /** @test */
    public function it_belongs_to_a_plan(): void
    {
        $this->assertInstanceOf(Plan::class, $this->pricing->plan);
        $this->assertEquals($this->plan->id, $this->pricing->plan->id);
    }

    /** @test */
    public function it_has_subscriptions_relationship(): void
    {
        $user = \AhmedEssam\SubSphere\Tests\Models\User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $subscription = Subscription::create([
            'subscriber_type' => get_class($user),
            'subscriber_id' => $user->id,
            'plan_id' => $this->plan->id,
            'plan_pricing_id' => $this->pricing->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $this->pricing->subscriptions);
        $this->assertTrue($this->pricing->subscriptions->contains($subscription));
    }

    /** @test */
    public function it_casts_attributes_correctly(): void
    {
        $pricing = PlanPricing::create([
            'plan_id' => $this->plan->id,
            'label' => 'Test Pricing',
            'duration_in_days' => '30',
            'price' => '199.99',
            'is_best_offer' => '1',
        ]);

        $this->assertIsInt($pricing->duration_in_days);
        $this->assertEquals(30, $pricing->duration_in_days);

        // Decimal casting returns a string for precision
        $this->assertIsString($pricing->price);
        $this->assertEquals('199.99', $pricing->price);

        $this->assertIsBool($pricing->is_best_offer);
        $this->assertTrue($pricing->is_best_offer);
    }

    /** @test */
    public function it_formats_price_with_decimal_precision(): void
    {
        $pricing = PlanPricing::create([
            'plan_id' => $this->plan->id,
            'label' => 'Test Pricing',
            'duration_in_days' => 30,
            'price' => 199.999, // More than 2 decimal places
            'is_best_offer' => false,
        ]);

        // The price should be cast as decimal:2
        $this->assertEquals(200.00, $pricing->price);
    }

    /** @test */
    public function it_can_handle_free_pricing(): void
    {
        $freePricing = PlanPricing::create([
            'plan_id' => $this->plan->id,
            'label' => 'Free',
            'duration_in_days' => 365,
            'price' => 0.00,
            'is_best_offer' => false,
        ]);

        $this->assertEquals(0.00, $freePricing->price);
        $this->assertFalse($freePricing->is_best_offer);
    }

    /** @test */
    public function multiple_pricings_can_belong_to_same_plan(): void
    {
        $monthlyPricing = PlanPricing::create([
            'plan_id' => $this->plan->id,
            'label' => 'Monthly',
            'duration_in_days' => 30,
            'price' => 99.99,
            'is_best_offer' => false,
        ]);

        $yearlyPricing = PlanPricing::create([
            'plan_id' => $this->plan->id,
            'label' => 'Yearly',
            'duration_in_days' => 365,
            'price' => 999.99,
            'is_best_offer' => true,
        ]);

        $planPricings = $this->plan->pricings;

        $this->assertCount(3, $planPricings); // Including the one from setUp
        $this->assertTrue($planPricings->contains($this->pricing));
        $this->assertTrue($planPricings->contains($monthlyPricing));
        $this->assertTrue($planPricings->contains($yearlyPricing));
    }

    /** @test */
    public function it_can_identify_best_offer(): void
    {
        $regularPricing = PlanPricing::create([
            'plan_id' => $this->plan->id,
            'label' => 'Regular',
            'duration_in_days' => 30,
            'price' => 99.99,
            'is_best_offer' => false,
        ]);

        $bestOfferPricing = PlanPricing::create([
            'plan_id' => $this->plan->id,
            'label' => 'Best Deal',
            'duration_in_days' => 365,
            'price' => 799.99,
            'is_best_offer' => true,
        ]);

        $this->assertFalse($regularPricing->is_best_offer);
        $this->assertTrue($bestOfferPricing->is_best_offer);
    }
}
