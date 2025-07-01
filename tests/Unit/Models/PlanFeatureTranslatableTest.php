<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Tests\Unit\Models;

use AhmedEssam\SubSphere\Models\Plan;
use AhmedEssam\SubSphere\Models\PlanFeature;
use AhmedEssam\SubSphere\Tests\TestCase;

class PlanFeatureTranslatableTest extends TestCase
{
    /** @test */
    public function it_handles_translatable_name_and_description_fields(): void
    {
        $plan = Plan::create([
            'slug' => 'test-plan',
            'name' => ['en' => 'Test Plan'],
            'description' => ['en' => 'Test Description'],
            'is_active' => true,
        ]);

        $feature = PlanFeature::create([
            'plan_id' => $plan->id,
            'key' => 'storage_limit',
            'name' => ['en' => 'Storage Limit', 'ar' => 'حد التخزين', 'fr' => 'Limite de stockage'],
            'description' => ['en' => 'Maximum storage allowed', 'ar' => 'الحد الأقصى للتخزين المسموح', 'fr' => 'Stockage maximum autorisé'],
            'value' => '100GB',
            'reset_period' => 'monthly',
        ]);

        // Test Spatie's translatable methods for name
        $this->assertEquals('Storage Limit', $feature->getTranslation('name', 'en'));
        $this->assertEquals('حد التخزين', $feature->getTranslation('name', 'ar'));
        $this->assertEquals('Limite de stockage', $feature->getTranslation('name', 'fr'));

        // Test Spatie's translatable methods for description
        $this->assertEquals('Maximum storage allowed', $feature->getTranslation('description', 'en'));
        $this->assertEquals('الحد الأقصى للتخزين المسموح', $feature->getTranslation('description', 'ar'));
        $this->assertEquals('Stockage maximum autorisé', $feature->getTranslation('description', 'fr'));

        // Test our helper methods
        $this->assertEquals('Storage Limit', $feature->getLocalizedName('en'));
        $this->assertEquals('حد التخزين', $feature->getLocalizedName('ar'));
        $this->assertEquals('Maximum storage allowed', $feature->getLocalizedDescription('en'));
        $this->assertEquals('الحد الأقصى للتخزين المسموح', $feature->getLocalizedDescription('ar'));

        // Test fallback behavior
        app()->setLocale('ar');
        $this->assertEquals('حد التخزين', $feature->getLocalizedName());
        $this->assertEquals('الحد الأقصى للتخزين المسموح', $feature->getLocalizedDescription());

        app()->setLocale('en');
        $this->assertEquals('Storage Limit', $feature->getLocalizedName());
        $this->assertEquals('Maximum storage allowed', $feature->getLocalizedDescription());
    }

    /** @test */
    public function it_handles_missing_translations_gracefully(): void
    {
        $plan = Plan::create([
            'slug' => 'test-plan',
            'name' => ['en' => 'Test Plan'],
            'description' => ['en' => 'Test Description'],
            'is_active' => true,
        ]);

        $feature = PlanFeature::create([
            'plan_id' => $plan->id,
            'key' => 'api_calls',
            'name' => ['en' => 'API Calls'], // Only English
            'description' => ['en' => 'Number of API calls allowed'], // Only English
            'value' => '1000',
            'reset_period' => 'monthly',
        ]);

        // Test fallback to English when requesting non-existent translation
        $this->assertEquals('API Calls', $feature->getLocalizedName('ar')); // Should fallback to 'en'
        $this->assertEquals('Number of API calls allowed', $feature->getLocalizedDescription('ar')); // Should fallback to 'en'

        // Test with completely missing translation
        $this->assertEquals('API Calls', $feature->getLocalizedName('de')); // Should fallback to 'en'
        $this->assertEquals('Number of API calls allowed', $feature->getLocalizedDescription('de')); // Should fallback to 'en'
    }

    /** @test */
    public function it_can_check_translation_availability(): void
    {
        $plan = Plan::create([
            'slug' => 'test-plan',
            'name' => ['en' => 'Test Plan'],
            'description' => ['en' => 'Test Description'],
            'is_active' => true,
        ]);

        $feature = PlanFeature::create([
            'plan_id' => $plan->id,
            'key' => 'bandwidth',
            'name' => ['en' => 'Bandwidth', 'ar' => 'عرض النطاق'],
            'description' => ['en' => 'Monthly bandwidth limit'],
            'value' => '100TB',
            'reset_period' => 'monthly',
        ]);

        $this->assertTrue($feature->hasTranslation('name', 'en'));
        $this->assertTrue($feature->hasTranslation('name', 'ar'));
        $this->assertFalse($feature->hasTranslation('name', 'fr'));

        $this->assertTrue($feature->hasTranslation('description', 'en'));
        $this->assertFalse($feature->hasTranslation('description', 'ar'));

        $nameTranslations = $feature->getTranslations('name');
        $nameLocales = array_keys($nameTranslations);
        $this->assertContains('en', $nameLocales);
        $this->assertContains('ar', $nameLocales);
        $this->assertCount(2, $nameLocales);

        $descriptionTranslations = $feature->getTranslations('description');
        $descriptionLocales = array_keys($descriptionTranslations);
        $this->assertContains('en', $descriptionLocales);
        $this->assertCount(1, $descriptionLocales);
    }
}
