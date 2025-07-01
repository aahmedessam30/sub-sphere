<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Tests\Unit\Models;

use AhmedEssam\SubSphere\Models\Plan;
use AhmedEssam\SubSphere\Models\PlanPricing;
use AhmedEssam\SubSphere\Tests\TestCase;

class PlanPricingTranslatableTest extends TestCase
{
    /** @test */
    public function it_handles_translatable_label_field(): void
    {
        $plan = Plan::create([
            'slug' => 'test-plan',
            'name' => ['en' => 'Test Plan'],
            'description' => ['en' => 'Test Description'],
            'is_active' => true,
        ]);

        $pricing = PlanPricing::create([
            'plan_id' => $plan->id,
            'label' => ['en' => 'Monthly', 'ar' => 'شهري', 'fr' => 'Mensuel'],
            'duration_in_days' => 30,
            'price' => 99.99,
            'is_best_offer' => false,
        ]);

        // Test Spatie's translatable methods
        $this->assertEquals('Monthly', $pricing->getTranslation('label', 'en'));
        $this->assertEquals('شهري', $pricing->getTranslation('label', 'ar'));
        $this->assertEquals('Mensuel', $pricing->getTranslation('label', 'fr'));

        // Test our helper method
        $this->assertEquals('Monthly', $pricing->getLocalizedLabel('en'));
        $this->assertEquals('شهري', $pricing->getLocalizedLabel('ar'));
        $this->assertEquals('Mensuel', $pricing->getLocalizedLabel('fr'));

        // Test fallback to default locale
        app()->setLocale('ar');
        $this->assertEquals('شهري', $pricing->getLocalizedLabel());

        app()->setLocale('en');
        $this->assertEquals('Monthly', $pricing->getLocalizedLabel());

        // Test that we have translations for languages with data
        $this->assertTrue($pricing->hasTranslation('label', 'en'));
        $this->assertTrue($pricing->hasTranslation('label', 'ar'));
        $this->assertTrue($pricing->hasTranslation('label', 'fr'));

        // For testing missing translations, we need to test a more specific case
        // since Spatie might return empty strings that our helper treats as valid
        $this->assertEquals('Monthly', $pricing->getLocalizedLabel('de')); // Should fallback to 'en'

        // Test available locales (using getTranslations)
        $labelTranslations = $pricing->getTranslations('label');
        $availableLocales = array_keys($labelTranslations);
        $this->assertContains('en', $availableLocales);
        $this->assertContains('ar', $availableLocales);
        $this->assertContains('fr', $availableLocales);
        $this->assertCount(3, $availableLocales);
    }

    /** @test */
    public function it_falls_back_to_default_locale_when_translation_missing(): void
    {
        $plan = Plan::create([
            'slug' => 'test-plan',
            'name' => ['en' => 'Test Plan'],
            'description' => ['en' => 'Test Description'],
            'is_active' => true,
        ]);

        $pricing = PlanPricing::create([
            'plan_id' => $plan->id,
            'label' => ['en' => 'Monthly'], // Only English translation
            'duration_in_days' => 30,
            'price' => 99.99,
            'is_best_offer' => false,
        ]);

        // Test fallback works correctly
        $this->assertEquals('Monthly', $pricing->getLocalizedLabel('ar')); // Should fallback to 'en'
        $this->assertEquals('Monthly', $pricing->getLocalizedLabel('fr')); // Should fallback to 'en'
        $this->assertEquals('Monthly', $pricing->getLocalizedLabel('de')); // Should fallback to 'en'
    }
}
