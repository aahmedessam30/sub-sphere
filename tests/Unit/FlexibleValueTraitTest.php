<?php

namespace AhmedEssam\SubSphere\Tests\Unit;

use AhmedEssam\SubSphere\Enums\FeatureResetPeriod;
use AhmedEssam\SubSphere\Models\Plan;
use AhmedEssam\SubSphere\Models\PlanFeature;
use AhmedEssam\SubSphere\Tests\TestCase;

class FlexibleValueTraitTest extends TestCase
{
    public function test_integer_value_handling()
    {
        $feature = new PlanFeature([
            'key' => 'api_calls',
            'value' => 10000,
            'reset_period' => FeatureResetPeriod::MONTHLY
        ]);

        $this->assertTrue($feature->isNumericFeature());
        $this->assertTrue($feature->isValueType('integer'));
        $this->assertEquals('integer', $feature->getValueType());
        $this->assertEquals(10000, $feature->getNumericValue());
        $this->assertEquals('10,000', $feature->getDisplayValue());
        $this->assertTrue($feature->isEnabled());
        $this->assertFalse($feature->isUnlimited());
    }

    public function test_boolean_value_handling()
    {
        $feature = new PlanFeature([
            'key' => 'advanced_analytics',
            'value' => true,
            'reset_period' => FeatureResetPeriod::NEVER
        ]);

        $this->assertTrue($feature->isBooleanFeature());
        $this->assertTrue($feature->isValueType('boolean'));
        $this->assertEquals('boolean', $feature->getValueType());
        $this->assertTrue($feature->getBooleanValue());
        $this->assertEquals('Included', $feature->getDisplayValue());
        $this->assertTrue($feature->isEnabled());
    }

    public function test_array_value_handling()
    {
        $allowedTypes = ['pdf', 'docx', 'jpg', 'png'];
        $feature = new PlanFeature([
            'key' => 'file_types',
            'value' => $allowedTypes,
            'reset_period' => FeatureResetPeriod::NEVER
        ]);

        $this->assertTrue($feature->isArrayFeature());
        $this->assertTrue($feature->isValueType('array'));
        $this->assertEquals('array', $feature->getValueType());
        $this->assertEquals($allowedTypes, $feature->getArrayValue());
        $this->assertEquals('4 items', $feature->getDisplayValue());
        $this->assertTrue($feature->isEnabled());
    }

    public function test_object_value_handling()
    {
        $config = (object) [
            'daily_limit' => 1000,
            'attachment_size' => 25
        ];

        $feature = new PlanFeature([
            'key' => 'email_config',
            'value' => $config,
            'reset_period' => FeatureResetPeriod::NEVER
        ]);

        $this->assertTrue($feature->isObjectFeature());
        $this->assertTrue($feature->isValueType('object'));
        $this->assertEquals('object', $feature->getValueType());
        $this->assertEquals($config, $feature->getObjectValue());
        $this->assertEquals('Custom configuration', $feature->getDisplayValue());
        $this->assertTrue($feature->isEnabled());
    }

    public function test_null_value_handling()
    {
        $feature = new PlanFeature([
            'key' => 'bandwidth',
            'value' => null,
            'reset_period' => FeatureResetPeriod::MONTHLY
        ]);

        $this->assertTrue($feature->isNullFeature());
        $this->assertTrue($feature->isValueType('null'));
        $this->assertEquals('null', $feature->getValueType());
        $this->assertEquals('Unlimited', $feature->getDisplayValue());
        $this->assertTrue($feature->isUnlimited());
    }

    public function test_string_value_handling()
    {
        $feature = new PlanFeature([
            'key' => 'support_level',
            'value' => 'premium',
            'reset_period' => FeatureResetPeriod::NEVER
        ]);

        $this->assertTrue($feature->isStringFeature());
        $this->assertTrue($feature->isValueType('string'));
        $this->assertEquals('string', $feature->getValueType());
        $this->assertEquals('premium', $feature->getStringValue());
        $this->assertEquals('premium', $feature->getDisplayValue());
        $this->assertTrue($feature->isEnabled());
    }

    public function test_float_value_handling()
    {
        $feature = new PlanFeature([
            'key' => 'storage_gb',
            'value' => 50.5,
            'reset_period' => FeatureResetPeriod::NEVER
        ]);

        $this->assertTrue($feature->isNumericFeature());
        $this->assertTrue($feature->isValueType('float'));
        $this->assertEquals('float', $feature->getValueType());
        $this->assertEquals(50.5, $feature->getNumericValue());
        $this->assertEquals('50.50', $feature->getDisplayValue());
        $this->assertTrue($feature->isEnabled());
    }

    public function test_value_conversion()
    {
        $feature = new PlanFeature([
            'key' => 'api_calls',
            'value' => 10000
        ]);

        // Test type conversions
        $this->assertEquals(10000, $feature->getValueAs('integer'));
        $this->assertEquals(10000.0, $feature->getValueAs('float'));
        $this->assertEquals('10000', $feature->getValueAs('string'));
        $this->assertEquals([10000], $feature->getValueAs('array'));
        $this->assertTrue($feature->getValueAs('boolean'));
    }

    public function test_value_comparison()
    {
        $feature1 = new PlanFeature(['value' => 1000]);
        $feature2 = new PlanFeature(['value' => 2000]);
        $feature3 = new PlanFeature(['value' => null]); // unlimited

        // Numeric comparison
        $this->assertTrue($feature2->isBetterThan($feature1->value));
        $this->assertTrue($feature1->isWorseThan($feature2->value));

        // Unlimited comparison
        $this->assertTrue($feature3->isBetterThan($feature1->value));
        $this->assertTrue($feature3->isBetterThan($feature2->value));
    }

    public function test_unlimited_detection()
    {
        // Null value
        $feature1 = new PlanFeature(['value' => null]);
        $this->assertTrue($feature1->isUnlimited());

        // Negative number
        $feature2 = new PlanFeature(['value' => -1]);
        $this->assertTrue($feature2->isUnlimited());

        // String indicators
        $feature3 = new PlanFeature(['value' => 'unlimited']);
        $this->assertTrue($feature3->isUnlimited());

        // Normal limits
        $feature4 = new PlanFeature(['value' => 1000]);
        $this->assertFalse($feature4->isUnlimited());
    }

    public function test_enabled_detection()
    {
        // Boolean true
        $feature1 = new PlanFeature(['value' => true]);
        $this->assertTrue($feature1->isEnabled());

        // Boolean false
        $feature2 = new PlanFeature(['value' => false]);
        $this->assertFalse($feature2->isEnabled());

        // Positive number
        $feature3 = new PlanFeature(['value' => 100]);
        $this->assertTrue($feature3->isEnabled());

        // Zero
        $feature4 = new PlanFeature(['value' => 0]);
        $this->assertFalse($feature4->isEnabled());

        // Non-empty string
        $feature5 = new PlanFeature(['value' => 'premium']);
        $this->assertTrue($feature5->isEnabled());

        // Disabled string
        $feature6 = new PlanFeature(['value' => 'disabled']);
        $this->assertFalse($feature6->isEnabled());
    }

    public function test_validation()
    {
        // Valid values
        $feature1 = new PlanFeature(['value' => 1000]);
        $this->assertTrue($feature1->validateValue());

        $feature2 = new PlanFeature(['value' => true]);
        $this->assertTrue($feature2->validateValue());

        $feature3 = new PlanFeature(['value' => ['option1', 'option2']]);
        $this->assertTrue($feature3->validateValue());

        // Invalid negative value (less than -1)
        $feature4 = new PlanFeature(['value' => -5]);
        $this->assertFalse($feature4->validateValue());
    }
}
