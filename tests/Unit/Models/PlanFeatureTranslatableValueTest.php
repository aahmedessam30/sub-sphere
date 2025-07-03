<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Tests\Unit\Models;

use AhmedEssam\SubSphere\Models\Plan;
use AhmedEssam\SubSphere\Models\PlanFeature;
use AhmedEssam\SubSphere\Tests\TestCase;

class PlanFeatureTranslatableValueTest extends TestCase
{
    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plan = Plan::create([
            'slug' => 'test-plan',
            'name' => ['en' => 'Test Plan'],
            'description' => ['en' => 'Test Description'],
            'is_active' => true,
        ]);
    }

    /** @test */
    public function it_can_store_and_retrieve_translatable_string_values(): void
    {
        $feature = PlanFeature::create([
            'plan_id' => $this->plan->id,
            'key' => 'support_level',
            'name' => ['en' => 'Support Level'],
            'description' => ['en' => 'Level of customer support'],
            'value' => [
                'en' => 'Premium Support',
                'ar' => 'دعم مميز',
                'fr' => 'Support Premium'
            ],
            'reset_period' => 'never',
        ]);

        // Test getting localized values
        $this->assertEquals('Premium Support', $feature->getLocalizedValue('en'));
        $this->assertEquals('دعم مميز', $feature->getLocalizedValue('ar'));
        $this->assertEquals('Support Premium', $feature->getLocalizedValue('fr'));

        // Test display values
        $this->assertEquals('Premium Support', $feature->getLocalizedDisplayValue('en'));
        $this->assertEquals('دعم مميز', $feature->getLocalizedDisplayValue('ar'));

        // Test checking for translations
        $this->assertTrue($feature->hasTranslatableValue());
        $this->assertTrue($feature->hasValueTranslation('en'));
        $this->assertTrue($feature->hasValueTranslation('ar'));
        $this->assertFalse($feature->hasValueTranslation('es'));

        // Test getting all locales
        $locales = $feature->getValueLocales();
        $this->assertContains('en', $locales);
        $this->assertContains('ar', $locales);
        $this->assertContains('fr', $locales);
        $this->assertCount(3, $locales);

        // Test getting all translations
        $translations = $feature->getValueTranslations();
        $this->assertEquals('Premium Support', $translations['en']);
        $this->assertEquals('دعم مميز', $translations['ar']);
        $this->assertEquals('Support Premium', $translations['fr']);
    }

    /** @test */
    public function it_can_store_translatable_numeric_values(): void
    {
        $feature = PlanFeature::create([
            'plan_id' => $this->plan->id,
            'key' => 'storage_limit_gb',
            'name' => ['en' => 'Storage Limit'],
            'description' => ['en' => 'Storage limit in GB'],
            'value' => [
                'en' => 100,        // GB for English users
                'ar' => 107374,     // KB for Arabic users (different unit preference)
                'fr' => 0.1         // TB for French users
            ],
            'reset_period' => 'monthly',
        ]);

        // Test different numeric types
        $this->assertEquals(100, $feature->getLocalizedValue('en'));
        $this->assertEquals(107374, $feature->getLocalizedValue('ar'));
        $this->assertEquals(0.1, $feature->getLocalizedValue('fr'));

        // Test that they maintain their types
        $this->assertIsInt($feature->getLocalizedValue('en'));
        $this->assertIsInt($feature->getLocalizedValue('ar'));
        $this->assertIsFloat($feature->getLocalizedValue('fr'));
    }

    /** @test */
    public function it_can_store_translatable_boolean_values(): void
    {
        $feature = PlanFeature::create([
            'plan_id' => $this->plan->id,
            'key' => 'advanced_features',
            'name' => ['en' => 'Advanced Features'],
            'description' => ['en' => 'Access to advanced features'],
            'value' => [
                'en' => true,
                'ar' => false,  // Different feature availability per region
                'fr' => true
            ],
            'reset_period' => 'never',
        ]);

        $this->assertTrue($feature->getLocalizedValue('en'));
        $this->assertFalse($feature->getLocalizedValue('ar'));
        $this->assertTrue($feature->getLocalizedValue('fr'));

        // Test display values for booleans
        $this->assertEquals('Included', $feature->getLocalizedDisplayValue('en'));
        $this->assertEquals('Not included', $feature->getLocalizedDisplayValue('ar'));
    }

    /** @test */
    public function it_can_store_translatable_array_values(): void
    {
        $feature = PlanFeature::create([
            'plan_id' => $this->plan->id,
            'key' => 'allowed_regions',
            'name' => ['en' => 'Allowed Regions'],
            'description' => ['en' => 'Regions where service is available'],
            'value' => [
                'en' => ['US', 'CA', 'UK'],
                'ar' => ['UAE', 'SA', 'EG'],
                'fr' => ['FR', 'BE', 'CH']
            ],
            'reset_period' => 'never',
        ]);

        $enRegions = $feature->getLocalizedValue('en');
        $arRegions = $feature->getLocalizedValue('ar');

        $this->assertIsArray($enRegions);
        $this->assertContains('US', $enRegions);
        $this->assertContains('CA', $enRegions);

        $this->assertIsArray($arRegions);
        $this->assertContains('UAE', $arRegions);
        $this->assertContains('SA', $arRegions);

        // Test display value for arrays
        $this->assertEquals('US, CA, UK', $feature->getLocalizedDisplayValue('en'));
        $this->assertEquals('UAE, SA, EG', $feature->getLocalizedDisplayValue('ar'));
    }

    /** @test */
    public function it_falls_back_to_default_locale_when_translation_missing(): void
    {
        $feature = PlanFeature::create([
            'plan_id' => $this->plan->id,
            'key' => 'api_rate_limit',
            'name' => ['en' => 'API Rate Limit'],
            'description' => ['en' => 'Maximum API calls per hour'],
            'value' => [
                'en' => 1000,
                'fr' => 500  // No Arabic translation
            ],
            'reset_period' => 'daily',
        ]);

        // Should fall back to English when Arabic is not available
        $this->assertEquals(1000, $feature->getLocalizedValue('ar'));
        $this->assertEquals(1000, $feature->getLocalizedValue('de')); // Non-existent locale
    }

    /** @test */
    public function it_supports_setting_individual_locale_values(): void
    {
        $feature = PlanFeature::create([
            'plan_id' => $this->plan->id,
            'key' => 'bandwidth_limit',
            'name' => ['en' => 'Bandwidth Limit'],
            'description' => ['en' => 'Monthly bandwidth limit'],
            'value' => [
                'en' => '100GB'
            ],
            'reset_period' => 'monthly',
        ]);

        // Add Arabic translation
        $feature->setValueForLocale('ar', '100 جيجابايت');
        $feature->save();

        // Reload from database
        $feature->refresh();

        $this->assertEquals('100GB', $feature->getLocalizedValue('en'));
        $this->assertEquals('100 جيجابايت', $feature->getLocalizedValue('ar'));
        $this->assertTrue($feature->hasValueTranslation('ar'));
    }

    /** @test */
    public function it_maintains_backwards_compatibility_with_non_translatable_values(): void
    {
        // Create feature with old-style single value
        $feature = PlanFeature::create([
            'plan_id' => $this->plan->id,
            'key' => 'legacy_feature',
            'name' => ['en' => 'Legacy Feature'],
            'description' => ['en' => 'A legacy feature'],
            'value' => 1000,  // Single value, not translatable
            'reset_period' => 'monthly',
        ]);

        // Should work with any locale
        $this->assertEquals(1000, $feature->getLocalizedValue('en'));
        $this->assertEquals(1000, $feature->getLocalizedValue('ar'));
        $this->assertEquals(1000, $feature->getLocalizedValue('fr'));

        // Should not be considered translatable
        $this->assertFalse($feature->hasTranslatableValue());
        $this->assertEmpty($feature->getValueLocales());
        $this->assertEmpty($feature->getValueTranslations());

        // But should still work with the original value property
        $this->assertEquals(1000, $feature->value);
    }

    /** @test */
    public function it_works_with_app_locale_changes(): void
    {
        $feature = PlanFeature::create([
            'plan_id' => $this->plan->id,
            'key' => 'welcome_message',
            'name' => ['en' => 'Welcome Message'],
            'description' => ['en' => 'Welcome message for users'],
            'value' => [
                'en' => 'Welcome to our service!',
                'ar' => 'مرحباً بك في خدمتنا!',
                'fr' => 'Bienvenue dans notre service!'
            ],
            'reset_period' => 'never',
        ]);

        // Test with different app locales
        app()->setLocale('en');
        $this->assertEquals('Welcome to our service!', $feature->getLocalizedValue());

        app()->setLocale('ar');
        $this->assertEquals('مرحباً بك في خدمتنا!', $feature->getLocalizedValue());

        app()->setLocale('fr');
        $this->assertEquals('Bienvenue dans notre service!', $feature->getLocalizedValue());

        // Reset to default
        app()->setLocale('en');
    }

    /** @test */
    public function it_handles_null_and_unlimited_values_per_locale(): void
    {
        $feature = PlanFeature::create([
            'plan_id' => $this->plan->id,
            'key' => 'file_uploads',
            'name' => ['en' => 'File Uploads'],
            'description' => ['en' => 'Number of file uploads allowed'],
            'value' => [
                'en' => null,    // Unlimited for English users
                'ar' => 100,     // Limited for Arabic users
                'fr' => null     // Unlimited for French users
            ],
            'reset_period' => 'daily',
        ]);

        $this->assertNull($feature->getLocalizedValue('en'));
        $this->assertEquals(100, $feature->getLocalizedValue('ar'));
        $this->assertNull($feature->getLocalizedValue('fr'));

        // Test display values
        $this->assertEquals('Unlimited', $feature->getLocalizedDisplayValue('en'));
        $this->assertEquals('100', $feature->getLocalizedDisplayValue('ar'));
        $this->assertEquals('Unlimited', $feature->getLocalizedDisplayValue('fr'));
    }

    /** @test */
    public function it_handles_null_value_correctly(): void
    {
        $feature = PlanFeature::create([
            'plan_id' => $this->plan->id,
            'key' => 'unlimited_feature',
            'name' => ['en' => 'Unlimited Feature', 'ar' => 'ميزة غير محدودة'],
            'description' => ['en' => 'An unlimited feature'],
            'value' => null,
            'reset_period' => 'never',
        ]);

        // Test that null value works for all locales
        $this->assertNull($feature->value);
        $this->assertNull($feature->getLocalizedValue('en'));
        $this->assertNull($feature->getLocalizedValue('ar'));
        $this->assertNull($feature->getLocalizedValue('fr'));
        $this->assertNull($feature->getLocalizedValue()); // Current locale

        // Test that it's not considered translatable
        $this->assertFalse($feature->hasTranslatableValue());
        $this->assertEmpty($feature->getValueLocales());
        $this->assertEmpty($feature->getValueTranslations());

        // Test display values
        $this->assertEquals('Unlimited', $feature->getLocalizedDisplayValue('en'));
        $this->assertEquals('Unlimited', $feature->getLocalizedDisplayValue('ar'));
        $this->assertEquals('Unlimited', $feature->getLocalizedDisplayValue('fr'));

        // Test that checking for specific translations returns false
        $this->assertFalse($feature->hasValueTranslation('en'));
        $this->assertFalse($feature->hasValueTranslation('ar'));

        // Test that raw database value is actually null
        $this->assertNull($feature->getRawOriginal('value'));
    }

    /** @test */
    public function it_handles_single_non_array_value_correctly(): void
    {
        $feature = PlanFeature::create([
            'plan_id' => $this->plan->id,
            'key' => 'api_rate_limit',
            'name' => ['en' => 'API Rate Limit', 'ar' => 'حد معدل API'],
            'description' => ['en' => 'API calls per hour'],
            'value' => 1000,
            'reset_period' => 'daily',
        ]);

        // Test that single value works for all locales
        $this->assertEquals(1000, $feature->value);
        $this->assertEquals(1000, $feature->getLocalizedValue('en'));
        $this->assertEquals(1000, $feature->getLocalizedValue('ar'));
        $this->assertEquals(1000, $feature->getLocalizedValue('fr'));
        $this->assertEquals(1000, $feature->getLocalizedValue()); // Current locale

        // Test that it's not considered translatable
        $this->assertFalse($feature->hasTranslatableValue());
        $this->assertEmpty($feature->getValueLocales());
        $this->assertEmpty($feature->getValueTranslations());

        // Test display values
        $this->assertEquals('1000', $feature->getLocalizedDisplayValue('en'));
        $this->assertEquals('1000', $feature->getLocalizedDisplayValue('ar'));
        $this->assertEquals('1000', $feature->getLocalizedDisplayValue('fr'));

        // Test that checking for specific translations returns false
        $this->assertFalse($feature->hasValueTranslation('en'));
        $this->assertFalse($feature->hasValueTranslation('ar'));

        // Test that raw database value is the flexible value structure
        $rawValue = $feature->getRawOriginal('value');
        $this->assertNotNull($rawValue);
        $decoded = json_decode($rawValue, true);
        $this->assertEquals('integer', $decoded['type']);
        $this->assertEquals(1000, $decoded['value']);
    }

    /** @test */
    public function it_handles_single_string_value_correctly(): void
    {
        $feature = PlanFeature::create([
            'plan_id' => $this->plan->id,
            'key' => 'support_email',
            'name' => ['en' => 'Support Email'],
            'description' => ['en' => 'Customer support email'],
            'value' => 'support@example.com',
            'reset_period' => 'never',
        ]);

        // Test that string value works for all locales
        $this->assertEquals('support@example.com', $feature->value);
        $this->assertEquals('support@example.com', $feature->getLocalizedValue('en'));
        $this->assertEquals('support@example.com', $feature->getLocalizedValue('ar'));
        $this->assertEquals('support@example.com', $feature->getLocalizedValue('fr'));

        // Test that it's not considered translatable
        $this->assertFalse($feature->hasTranslatableValue());

        // Test display value
        $this->assertEquals('support@example.com', $feature->getLocalizedDisplayValue('en'));

        // Test that raw database value has correct structure
        $rawValue = $feature->getRawOriginal('value');
        $decoded = json_decode($rawValue, true);
        $this->assertEquals('string', $decoded['type']);
        $this->assertEquals('support@example.com', $decoded['value']);
    }

    /** @test */
    public function it_handles_single_boolean_value_correctly(): void
    {
        $feature = PlanFeature::create([
            'plan_id' => $this->plan->id,
            'key' => 'premium_support',
            'name' => ['en' => 'Premium Support'],
            'description' => ['en' => 'Access to premium support'],
            'value' => true,
            'reset_period' => 'never',
        ]);

        // Test that boolean value works for all locales
        $this->assertTrue($feature->value);
        $this->assertTrue($feature->getLocalizedValue('en'));
        $this->assertTrue($feature->getLocalizedValue('ar'));
        $this->assertTrue($feature->getLocalizedValue('fr'));

        // Test that it's not considered translatable
        $this->assertFalse($feature->hasTranslatableValue());

        // Test display value
        $this->assertEquals('Included', $feature->getLocalizedDisplayValue('en'));

        // Test with false value
        $feature->value = false;
        $feature->save();
        $feature->refresh();

        $this->assertFalse($feature->value);
        $this->assertFalse($feature->getLocalizedValue('en'));
        $this->assertEquals('Not included', $feature->getLocalizedDisplayValue('en'));
    }

    /** @test */
    public function it_handles_single_array_value_correctly(): void
    {
        $allowedRegions = ['US', 'CA', 'UK'];

        $feature = PlanFeature::create([
            'plan_id' => $this->plan->id,
            'key' => 'allowed_regions',
            'name' => ['en' => 'Allowed Regions'],
            'description' => ['en' => 'Regions where service is available'],
            'value' => $allowedRegions,
            'reset_period' => 'never',
        ]);

        // Test that array value works for all locales
        $this->assertEquals($allowedRegions, $feature->value);
        $this->assertEquals($allowedRegions, $feature->getLocalizedValue('en'));
        $this->assertEquals($allowedRegions, $feature->getLocalizedValue('ar'));
        $this->assertEquals($allowedRegions, $feature->getLocalizedValue('fr'));

        // Test that it's not considered translatable
        $this->assertFalse($feature->hasTranslatableValue());

        // Test display value
        $this->assertEquals('US, CA, UK', $feature->getLocalizedDisplayValue('en'));

        // Test that raw database value has correct structure
        $rawValue = $feature->getRawOriginal('value');
        $decoded = json_decode($rawValue, true);
        $this->assertEquals('array', $decoded['type']);
        $this->assertEquals($allowedRegions, $decoded['value']);
    }

    /** @test */
    public function it_handles_null_value_as_unlimited(): void
    {
        $feature = PlanFeature::create([
            'plan_id' => $this->plan->id,
            'key' => 'storage_space',
            'name' => ['en' => 'Storage Space'],
            'description' => ['en' => 'Available storage space'],
            'value' => null,
            'reset_period' => 'never',
        ]);

        // Null value should be treated as unlimited for all locales
        $this->assertNull($feature->getLocalizedValue('en'));
        $this->assertNull($feature->getLocalizedValue('ar'));
        $this->assertNull($feature->getLocalizedValue('fr'));
        $this->assertNull($feature->getLocalizedValue('de')); // Non-existent locale

        // Display value should show "Unlimited"
        $this->assertEquals('Unlimited', $feature->getLocalizedDisplayValue('en'));
        $this->assertEquals('Unlimited', $feature->getLocalizedDisplayValue('ar'));

        // isUnlimited should return true
        $this->assertTrue($feature->isUnlimited());
        $this->assertTrue($feature->isUnlimited('en'));
        $this->assertTrue($feature->isUnlimited('ar'));

        // hasValueTranslation should return false for all locales
        $this->assertFalse($feature->hasValueTranslation('en'));
        $this->assertFalse($feature->hasValueTranslation('ar'));
    }

    /** @test */
    public function it_handles_single_integer_value_for_all_locales(): void
    {
        $feature = PlanFeature::create([
            'plan_id' => $this->plan->id,
            'key' => 'api_calls',
            'name' => ['en' => 'API Calls'],
            'description' => ['en' => 'Maximum API calls per day'],
            'value' => 1000,
            'reset_period' => 'daily',
        ]);

        // Same value should be returned for all locales
        $this->assertEquals(1000, $feature->getLocalizedValue('en'));
        $this->assertEquals(1000, $feature->getLocalizedValue('ar'));
        $this->assertEquals(1000, $feature->getLocalizedValue('fr'));
        $this->assertEquals(1000, $feature->getLocalizedValue('de')); // Non-existent locale

        // Display value should be formatted
        $this->assertEquals('1000', $feature->getLocalizedDisplayValue('en'));
        $this->assertEquals('1000', $feature->getLocalizedDisplayValue('ar'));

        // Should not be unlimited
        $this->assertFalse($feature->isUnlimited());
        $this->assertFalse($feature->isUnlimited('en'));

        // hasValueTranslation should return false (not a translatable array)
        $this->assertFalse($feature->hasValueTranslation('en'));
        $this->assertFalse($feature->hasValueTranslation('ar'));
    }

    /** @test */
    public function it_handles_single_string_value_for_all_locales(): void
    {
        $feature = PlanFeature::create([
            'plan_id' => $this->plan->id,
            'key' => 'support_type',
            'name' => ['en' => 'Support Type'],
            'description' => ['en' => 'Type of customer support'],
            'value' => 'email-only',
            'reset_period' => 'never',
        ]);

        // Same string value should be returned for all locales
        $this->assertEquals('email-only', $feature->getLocalizedValue('en'));
        $this->assertEquals('email-only', $feature->getLocalizedValue('ar'));
        $this->assertEquals('email-only', $feature->getLocalizedValue('fr'));

        // Display value should be the same
        $this->assertEquals('email-only', $feature->getLocalizedDisplayValue('en'));
        $this->assertEquals('email-only', $feature->getLocalizedDisplayValue('ar'));

        // Should not be unlimited
        $this->assertFalse($feature->isUnlimited());

        // hasValueTranslation should return false
        $this->assertFalse($feature->hasValueTranslation('en'));
        $this->assertFalse($feature->hasValueTranslation('ar'));
    }

    /** @test */
    public function it_handles_single_boolean_value_for_all_locales(): void
    {
        $feature = PlanFeature::create([
            'plan_id' => $this->plan->id,
            'key' => 'priority_support',
            'name' => ['en' => 'Priority Support'],
            'description' => ['en' => 'Access to priority support'],
            'value' => true,
            'reset_period' => 'never',
        ]);

        // Same boolean value should be returned for all locales
        $this->assertTrue($feature->getLocalizedValue('en'));
        $this->assertTrue($feature->getLocalizedValue('ar'));
        $this->assertTrue($feature->getLocalizedValue('fr'));

        // Display value should show "Included"
        $this->assertEquals('Included', $feature->getLocalizedDisplayValue('en'));
        $this->assertEquals('Included', $feature->getLocalizedDisplayValue('ar'));

        // Test with false value
        $feature->value = false;
        $feature->save();
        $feature->refresh();

        $this->assertFalse($feature->getLocalizedValue('en'));
        $this->assertFalse($feature->getLocalizedValue('ar'));
        $this->assertEquals('Not included', $feature->getLocalizedDisplayValue('en'));
        $this->assertEquals('Not included', $feature->getLocalizedDisplayValue('ar'));

        // Should not be unlimited
        $this->assertFalse($feature->isUnlimited());

        // hasValueTranslation should return false
        $this->assertFalse($feature->hasValueTranslation('en'));
        $this->assertFalse($feature->hasValueTranslation('ar'));
    }

    /** @test */
    public function it_handles_single_array_value_for_all_locales(): void
    {
        $regions = ['US', 'CA', 'UK', 'AU'];

        $feature = PlanFeature::create([
            'plan_id' => $this->plan->id,
            'key' => 'global_regions',
            'name' => ['en' => 'Global Regions'],
            'description' => ['en' => 'Available regions worldwide'],
            'value' => $regions,
            'reset_period' => 'never',
        ]);

        // Same array should be returned for all locales
        $this->assertEquals($regions, $feature->getLocalizedValue('en'));
        $this->assertEquals($regions, $feature->getLocalizedValue('ar'));
        $this->assertEquals($regions, $feature->getLocalizedValue('fr'));

        // Display value should be comma-separated
        $expectedDisplay = 'US, CA, UK, AU';
        $this->assertEquals($expectedDisplay, $feature->getLocalizedDisplayValue('en'));
        $this->assertEquals($expectedDisplay, $feature->getLocalizedDisplayValue('ar'));

        // Should not be unlimited
        $this->assertFalse($feature->isUnlimited());

        // hasValueTranslation should return false (not a translatable array)
        $this->assertFalse($feature->hasValueTranslation('en'));
        $this->assertFalse($feature->hasValueTranslation('ar'));
    }

    /** @test */
    public function it_distinguishes_between_translatable_and_non_translatable_arrays(): void
    {
        // Non-translatable array (simple values)
        $simpleArrayFeature = PlanFeature::create([
            'plan_id' => $this->plan->id,
            'key' => 'simple_array',
            'name' => ['en' => 'Simple Array'],
            'description' => ['en' => 'A simple array value'],
            'value' => ['item1', 'item2', 'item3'],
            'reset_period' => 'never',
        ]);

        // Translatable array (locale keys)
        $translatableArrayFeature = PlanFeature::create([
            'plan_id' => $this->plan->id,
            'key' => 'translatable_array',
            'name' => ['en' => 'Translatable Array'],
            'description' => ['en' => 'A translatable array value'],
            'value' => [
                'en' => ['item1', 'item2'],
                'ar' => ['عنصر1', 'عنصر2'],
            ],
            'reset_period' => 'never',
        ]);

        // Simple array should return same value for all locales
        $this->assertEquals(['item1', 'item2', 'item3'], $simpleArrayFeature->getLocalizedValue('en'));
        $this->assertEquals(['item1', 'item2', 'item3'], $simpleArrayFeature->getLocalizedValue('ar'));
        $this->assertFalse($simpleArrayFeature->hasValueTranslation('en'));

        // Translatable array should return different values per locale
        $this->assertEquals(['item1', 'item2'], $translatableArrayFeature->getLocalizedValue('en'));
        $this->assertEquals(['عنصر1', 'عنصر2'], $translatableArrayFeature->getLocalizedValue('ar'));
        $this->assertTrue($translatableArrayFeature->hasValueTranslation('en'));
        $this->assertTrue($translatableArrayFeature->hasValueTranslation('ar'));
    }

    /** @test */
    public function it_handles_edge_case_with_numeric_keys_in_array(): void
    {
        // Array with numeric keys should be treated as non-translatable
        $feature = PlanFeature::create([
            'plan_id' => $this->plan->id,
            'key' => 'numeric_keys',
            'name' => ['en' => 'Numeric Keys'],
            'description' => ['en' => 'Array with numeric keys'],
            'value' => [
                0 => 'first',
                1 => 'second',
                2 => 'third',
            ],
            'reset_period' => 'never',
        ]);

        // Should be treated as non-translatable
        $expected = [0 => 'first', 1 => 'second', 2 => 'third'];
        $this->assertEquals($expected, $feature->getLocalizedValue('en'));
        $this->assertEquals($expected, $feature->getLocalizedValue('ar'));
        $this->assertFalse($feature->hasValueTranslation('en'));
    }

    /** @test */
    public function it_handles_mixed_keys_in_array(): void
    {
        // Array with both locale-like and non-locale keys
        $mixedValue = [
            'en' => 'english',
            'config' => 'some_config',
            'ar' => 'arabic',
            'other' => 'other_value',
        ];

        $feature = PlanFeature::create([
            'plan_id' => $this->plan->id,
            'key' => 'mixed_keys',
            'name' => ['en' => 'Mixed Keys'],
            'description' => ['en' => 'Array with mixed keys'],
            'value' => $mixedValue,
            'reset_period' => 'never',
        ]);

        // Should be treated as non-translatable since not ALL keys are valid locales
        $this->assertEquals($mixedValue, $feature->getLocalizedValue('en'));
        $this->assertEquals($mixedValue, $feature->getLocalizedValue('ar'));
        $this->assertFalse($feature->hasValueTranslation('en'));
        $this->assertFalse($feature->hasValueTranslation('ar'));

        // Test with only valid locale keys
        $validLocaleFeature = PlanFeature::create([
            'plan_id' => $this->plan->id,
            'key' => 'valid_locale_keys',
            'name' => ['en' => 'Valid Locale Keys'],
            'description' => ['en' => 'Array with only valid locale keys'],
            'value' => [
                'en' => 'english',
                'ar' => 'arabic',
                'fr' => 'french',
                'es' => 'spanish',
            ],
            'reset_period' => 'never',
        ]);

        // This should be treated as translatable
        $this->assertEquals('english', $validLocaleFeature->getLocalizedValue('en'));
        $this->assertEquals('arabic', $validLocaleFeature->getLocalizedValue('ar'));
        $this->assertTrue($validLocaleFeature->hasValueTranslation('en'));
        $this->assertTrue($validLocaleFeature->hasValueTranslation('ar'));

        // Non-existent locale should fall back to default (en)
        $this->assertEquals('english', $validLocaleFeature->getLocalizedValue('de'));
    }

    /** @test */
    public function it_maintains_backward_compatibility_when_converting_single_to_translatable(): void
    {
        // Start with a single value
        $feature = PlanFeature::create([
            'plan_id' => $this->plan->id,
            'key' => 'conversion_test',
            'name' => ['en' => 'Conversion Test'],
            'description' => ['en' => 'Test converting from single to translatable'],
            'value' => 500,
            'reset_period' => 'monthly',
        ]);

        // Initially should work as single value
        $this->assertEquals(500, $feature->getLocalizedValue('en'));
        $this->assertEquals(500, $feature->getLocalizedValue('ar'));
        $this->assertFalse($feature->hasValueTranslation('en'));

        // Convert to translatable by setting an array with locale keys
        $feature->value = [
            'en' => 500,
            'ar' => 300,
            'fr' => 400,
        ];
        $feature->save();
        $feature->refresh();

        // Now should work as translatable
        $this->assertEquals(500, $feature->getLocalizedValue('en'));
        $this->assertEquals(300, $feature->getLocalizedValue('ar'));
        $this->assertEquals(400, $feature->getLocalizedValue('fr'));
        $this->assertTrue($feature->hasValueTranslation('en'));
        $this->assertTrue($feature->hasValueTranslation('ar'));
    }

    /** @test */
    public function it_maintains_backward_compatibility_when_converting_translatable_to_single(): void
    {
        // Start with translatable value
        $feature = PlanFeature::create([
            'plan_id' => $this->plan->id,
            'key' => 'reverse_conversion_test',
            'name' => ['en' => 'Reverse Conversion Test'],
            'description' => ['en' => 'Test converting from translatable to single'],
            'value' => [
                'en' => 'Premium',
                'ar' => 'مميز',
                'fr' => 'Premium',
            ],
            'reset_period' => 'never',
        ]);

        // Initially should work as translatable
        $this->assertEquals('Premium', $feature->getLocalizedValue('en'));
        $this->assertEquals('مميز', $feature->getLocalizedValue('ar'));
        $this->assertTrue($feature->hasValueTranslation('en'));

        // Convert to single value
        $feature->value = 'Standard';
        $feature->save();
        $feature->refresh();

        // Now should work as single value for all locales
        $this->assertEquals('Standard', $feature->getLocalizedValue('en'));
        $this->assertEquals('Standard', $feature->getLocalizedValue('ar'));
        $this->assertEquals('Standard', $feature->getLocalizedValue('fr'));
        $this->assertFalse($feature->hasValueTranslation('en'));
    }

    /** @test */
    public function it_handles_empty_array_as_single_value(): void
    {
        $feature = PlanFeature::create([
            'plan_id' => $this->plan->id,
            'key' => 'empty_array_test',
            'name' => ['en' => 'Empty Array Test'],
            'description' => ['en' => 'Test with empty array'],
            'value' => [],
            'reset_period' => 'never',
        ]);

        // Empty array should be treated as single value
        $this->assertEquals([], $feature->getLocalizedValue('en'));
        $this->assertEquals([], $feature->getLocalizedValue('ar'));
        $this->assertFalse($feature->hasValueTranslation('en'));

        // Display should show empty
        $this->assertEquals('', $feature->getLocalizedDisplayValue('en'));
    }

    /** @test */
    public function it_handles_zero_and_false_values_correctly(): void
    {
        // Test with zero
        $zeroFeature = PlanFeature::create([
            'plan_id' => $this->plan->id,
            'key' => 'zero_value',
            'name' => ['en' => 'Zero Value'],
            'description' => ['en' => 'Feature with zero value'],
            'value' => 0,
            'reset_period' => 'daily',
        ]);

        $this->assertEquals(0, $zeroFeature->getLocalizedValue('en'));
        $this->assertEquals('0', $zeroFeature->getLocalizedDisplayValue('en'));
        $this->assertFalse($zeroFeature->isUnlimited());

        // Test with false
        $falseFeature = PlanFeature::create([
            'plan_id' => $this->plan->id,
            'key' => 'false_value',
            'name' => ['en' => 'False Value'],
            'description' => ['en' => 'Feature with false value'],
            'value' => false,
            'reset_period' => 'never',
        ]);

        $this->assertFalse($falseFeature->getLocalizedValue('en'));
        $this->assertEquals('Not included', $falseFeature->getLocalizedDisplayValue('en'));
        $this->assertFalse($falseFeature->isUnlimited());
    }

    /** @test */
    public function it_can_update_specific_locale_values_without_affecting_others(): void
    {
        $feature = PlanFeature::create([
            'plan_id' => $this->plan->id,
            'key' => 'partial_update_test',
            'name' => ['en' => 'Partial Update Test'],
            'description' => ['en' => 'Test updating specific locales'],
            'value' => [
                'en' => 1000,
                'ar' => 800,
                'fr' => 900,
            ],
            'reset_period' => 'monthly',
        ]);

        // Update only Arabic value
        $feature->setValueForLocale('ar', 1200);
        $feature->save();
        $feature->refresh();

        // Arabic should be updated, others unchanged
        $this->assertEquals(1000, $feature->getLocalizedValue('en'));
        $this->assertEquals(1200, $feature->getLocalizedValue('ar'));
        $this->assertEquals(900, $feature->getLocalizedValue('fr'));

        // Add new locale
        $feature->setValueForLocale('es', 850);
        $feature->save();
        $feature->refresh();

        $this->assertEquals(850, $feature->getLocalizedValue('es'));
        $this->assertTrue($feature->hasValueTranslation('es'));
    }
}
