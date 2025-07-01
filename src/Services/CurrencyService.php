<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Services;

/**
 * Currency Helper Service
 * 
 * Provides currency-related utilities for multi-currency support.
 */
class CurrencyService
{
    /**
     * Get the default currency from configuration
     */
    public static function getDefaultCurrency(): string
    {
        return config('sub-sphere.currency.default', 'EGP');
    }

    /**
     * Get all supported currencies
     */
    public static function getSupportedCurrencies(): array
    {
        return config('sub-sphere.currency.supported_currencies', ['EGP']);
    }

    /**
     * Check if a currency is supported
     */
    public static function isCurrencySupported(string $currencyCode): bool
    {
        return in_array(strtoupper($currencyCode), self::getSupportedCurrencies());
    }

    /**
     * Get currency symbol for a given currency code
     */
    public static function getCurrencySymbol(string $currencyCode): string
    {
        $symbols = config('sub-sphere.currency.currency_symbols', []);
        return $symbols[strtoupper($currencyCode)] ?? strtoupper($currencyCode);
    }

    /**
     * Format price with currency symbol
     */
    public static function formatPrice(float|string $amount, string $currencyCode): string
    {
        $amount = (float) $amount; // Convert string to float if needed
        $symbol = self::getCurrencySymbol($currencyCode);
        return $symbol . number_format($amount, 2);
    }

    /**
     * Normalize currency code to uppercase
     */
    public static function normalizeCurrencyCode(string $currencyCode): string
    {
        return strtoupper(trim($currencyCode));
    }

    /**
     * Get fallback currency or throw exception
     */
    public static function resolveCurrency(?string $currency = null): string
    {
        if ($currency && self::isCurrencySupported($currency)) {
            return self::normalizeCurrencyCode($currency);
        }

        if (config('sub-sphere.currency.fallback_to_default', true)) {
            return self::getDefaultCurrency();
        }

        throw new \InvalidArgumentException(__('sub-sphere::subscription.config.currency_not_supported', ['currency' => $currency]));
    }

    /**
     * Convert amount between currencies (placeholder for future implementation)
     */
    public static function convertAmount(float $amount, string $fromCurrency, string $toCurrency): float
    {
        // Future: integrate with currency conversion API
        // For now, return the same amount
        return $amount;
    }

    /**
     * Validate currency format (3-letter ISO code)
     */
    public static function validateCurrencyFormat(string $currencyCode): bool
    {
        return preg_match('/^[A-Z]{3}$/', strtoupper($currencyCode)) === 1;
    }

    /**
     * Get localized currency name
     */
    public static function getCurrencyName(string $currencyCode, string $locale = 'en'): string
    {
        $names = config('sub-sphere.currency.currency_names', []);
        return $names[strtoupper($currencyCode)][$locale] ?? strtoupper($currencyCode);
    }

    /**
     * Format amount with proper decimal places for currency
     */
    public static function formatAmountForCurrency(float $amount, string $currencyCode): string
    {
        $decimalPlaces = config('sub-sphere.currency.decimal_places', []);
        $decimals = $decimalPlaces[strtoupper($currencyCode)] ?? 2;

        return number_format($amount, $decimals);
    }

    /**
     * Validate service configuration
     */
    public static function validateConfiguration(): array
    {
        $errors = [];

        // Check default currency
        $defaultCurrency = config('sub-sphere.currency.default');
        if (!$defaultCurrency) {
            $errors[] = __('sub-sphere::subscription.config.default_currency_not_configured');
        } elseif (!self::validateCurrencyFormat($defaultCurrency)) {
            $errors[] = __('sub-sphere::subscription.config.default_currency_format_invalid');
        }

        // Check supported currencies
        $supportedCurrencies = config('sub-sphere.currency.supported_currencies', []);
        if (empty($supportedCurrencies)) {
            $errors[] = __('sub-sphere::subscription.config.no_supported_currencies_configured');
        }

        // Check if default currency is in supported list
        if ($defaultCurrency && !empty($supportedCurrencies) && !in_array($defaultCurrency, $supportedCurrencies)) {
            $errors[] = __('sub-sphere::subscription.config.default_currency_not_in_supported_list');
        }

        return $errors;
    }

    /**
     * Get configuration status
     */
    public static function getConfigurationStatus(): array
    {
        return [
            'default_currency' => self::getDefaultCurrency(),
            'supported_currencies' => self::getSupportedCurrencies(),
            'currency_symbols_count' => count(config('sub-sphere.currency.currency_symbols', [])),
            'fallback_enabled' => config('sub-sphere.currency.fallback_to_default', true),
            'configuration_errors' => self::validateConfiguration(),
        ];
    }
}
