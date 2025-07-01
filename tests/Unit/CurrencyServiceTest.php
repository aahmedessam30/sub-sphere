<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Tests\Unit;

use AhmedEssam\SubSphere\Services\CurrencyService;
use AhmedEssam\SubSphere\Tests\TestCase;
use Illuminate\Support\Facades\Config;

/**
 * Test CurrencyService functionality
 * Tests currency validation, formatting, and configuration management
 */
class CurrencyServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set up test configuration
        Config::set('sub-sphere.currency.default', 'USD');
        Config::set('sub-sphere.currency.supported_currencies', ['USD', 'EUR', 'EGP']);
        Config::set('sub-sphere.currency.currency_symbols', [
            'USD' => '$',
            'EUR' => '€',
            'EGP' => 'LE',
        ]);
        Config::set('sub-sphere.currency.fallback_to_default', true);
    }

    /** @test */
    public function it_can_get_default_currency(): void
    {
        $currency = CurrencyService::getDefaultCurrency();
        $this->assertEquals('USD', $currency);
    }

    /** @test */
    public function it_can_get_supported_currencies(): void
    {
        $currencies = CurrencyService::getSupportedCurrencies();
        $this->assertEquals(['USD', 'EUR', 'EGP'], $currencies);
    }

    /** @test */
    public function it_can_check_if_currency_is_supported(): void
    {
        $this->assertTrue(CurrencyService::isCurrencySupported('USD'));
        $this->assertTrue(CurrencyService::isCurrencySupported('usd')); // Case insensitive
        $this->assertFalse(CurrencyService::isCurrencySupported('GBP'));
    }

    /** @test */
    public function it_can_get_currency_symbol(): void
    {
        $this->assertEquals('$', CurrencyService::getCurrencySymbol('USD'));
        $this->assertEquals('€', CurrencyService::getCurrencySymbol('EUR'));
        $this->assertEquals('GBP', CurrencyService::getCurrencySymbol('GBP')); // Fallback to code
    }

    /** @test */
    public function it_can_format_price_with_currency(): void
    {
        $formatted = CurrencyService::formatPrice(99.99, 'USD');
        $this->assertEquals('$99.99', $formatted);

        $formatted = CurrencyService::formatPrice(1234.5, 'EUR');
        $this->assertEquals('€1,234.50', $formatted);
    }

    /** @test */
    public function it_can_normalize_currency_code(): void
    {
        $this->assertEquals('USD', CurrencyService::normalizeCurrencyCode('usd'));
        $this->assertEquals('EUR', CurrencyService::normalizeCurrencyCode(' eur '));
        $this->assertEquals('EGP', CurrencyService::normalizeCurrencyCode('EgP'));
    }

    /** @test */
    public function it_can_resolve_currency_with_fallback(): void
    {
        $this->assertEquals('USD', CurrencyService::resolveCurrency('USD'));
        $this->assertEquals('USD', CurrencyService::resolveCurrency(null)); // Fallback to default
    }

    /** @test */
    public function it_throws_exception_when_currency_not_supported_and_no_fallback(): void
    {
        Config::set('sub-sphere.currency.fallback_to_default', false);

        $this->expectException(\InvalidArgumentException::class);
        CurrencyService::resolveCurrency('GBP');
    }

    /** @test */
    public function it_can_validate_currency_format(): void
    {
        $this->assertTrue(CurrencyService::validateCurrencyFormat('USD'));
        $this->assertTrue(CurrencyService::validateCurrencyFormat('eur'));
        $this->assertFalse(CurrencyService::validateCurrencyFormat('US'));
        $this->assertFalse(CurrencyService::validateCurrencyFormat('USDD'));
        $this->assertFalse(CurrencyService::validateCurrencyFormat('123'));
    }

    /** @test */
    public function it_can_get_currency_name(): void
    {
        Config::set('sub-sphere.currency.currency_names', [
            'USD' => ['en' => 'US Dollar', 'ar' => 'دولار أمريكي'],
            'EUR' => ['en' => 'Euro', 'ar' => 'يورو'],
        ]);

        $this->assertEquals('US Dollar', CurrencyService::getCurrencyName('USD', 'en'));
        $this->assertEquals('دولار أمريكي', CurrencyService::getCurrencyName('USD', 'ar'));
        $this->assertEquals('EGP', CurrencyService::getCurrencyName('EGP', 'en')); // Fallback
    }

    /** @test */
    public function it_can_format_amount_for_currency(): void
    {
        Config::set('sub-sphere.currency.decimal_places', [
            'USD' => 2,
            'JPY' => 0,
            'BHD' => 3,
        ]);

        $this->assertEquals('99.99', CurrencyService::formatAmountForCurrency(99.99, 'USD'));
        $this->assertEquals('100', CurrencyService::formatAmountForCurrency(99.99, 'JPY'));
        $this->assertEquals('99.990', CurrencyService::formatAmountForCurrency(99.99, 'BHD'));
        $this->assertEquals('99.99', CurrencyService::formatAmountForCurrency(99.99, 'GBP')); // Default 2 decimals
    }

    /** @test */
    public function it_can_validate_configuration(): void
    {
        $errors = CurrencyService::validateConfiguration();
        $this->assertEmpty($errors);

        // Test with invalid configuration
        Config::set('sub-sphere.currency.default', '');
        $errors = CurrencyService::validateConfiguration();
        $this->assertNotEmpty($errors);
        $this->assertContains('Default currency not configured', $errors);
    }

    /** @test */
    public function it_can_get_configuration_status(): void
    {
        $status = CurrencyService::getConfigurationStatus();

        $this->assertArrayHasKey('default_currency', $status);
        $this->assertArrayHasKey('supported_currencies', $status);
        $this->assertArrayHasKey('currency_symbols_count', $status);
        $this->assertArrayHasKey('fallback_enabled', $status);
        $this->assertArrayHasKey('configuration_errors', $status);

        $this->assertEquals('USD', $status['default_currency']);
        $this->assertEquals(['USD', 'EUR', 'EGP'], $status['supported_currencies']);
        $this->assertTrue($status['fallback_enabled']);
    }

    /** @test */
    public function it_handles_missing_currency_symbols_gracefully(): void
    {
        Config::set('sub-sphere.currency.currency_symbols', []);

        $symbol = CurrencyService::getCurrencySymbol('USD');
        $this->assertEquals('USD', $symbol);
    }

    /** @test */
    public function it_can_convert_amount_between_currencies(): void
    {
        // For now, this is a placeholder that returns the same amount
        $converted = CurrencyService::convertAmount(100.0, 'USD', 'EUR');
        $this->assertEquals(100.0, $converted);
    }
}
