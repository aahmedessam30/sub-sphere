<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Traits;

/**
 * Comprehensive trait for handling flexible value operations with full translation support
 * 
 * This trait provides complete functionality for working with flexible value types in plan features.
 * It handles both single values and translatable values seamlessly, supporting all PHP data types
 * (int, float, bool, string, array, object, null) with locale-aware operations.
 * 
 * Features:
 * - Type checking and conversion for all PHP types
 * - Locale-aware value access with automatic fallback
 * - Translation structure management
 * - Value comparison and validation
 * - Human-readable display formatting
 * - Full backward compatibility with existing APIs
 * 
 * This trait consolidates all flexible value functionality into a single, comprehensive
 * solution that eliminates code duplication while maintaining full backward compatibility.
 * 
 * All methods support an optional locale parameter for multi-language functionality.
 */
trait HandlesFlexibleValues
{
    /**
     * Get the value as a specific type
     */
    public function getValueAs(string $type, ?string $locale = null): mixed
    {
        $value = $this->getValue($locale);

        return match ($type) {
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'bool', 'boolean' => (bool) $value,
            'string' => (string) $value,
            'array' => is_array($value) ? $value : [$value],
            'object' => is_object($value) ? $value : (object) ['value' => $value],
            default => $value
        };
    }

    /**
     * Check if the value is of a specific type
     */
    public function isValueType(string $type, ?string $locale = null): bool
    {
        $value = $this->getValue($locale);

        return match ($type) {
            'null' => is_null($value),
            'int', 'integer' => is_int($value),
            'float', 'double' => is_float($value),
            'bool', 'boolean' => is_bool($value),
            'string' => is_string($value),
            'array' => is_array($value),
            'object' => is_object($value),
            'numeric' => is_numeric($value),
            default => false
        };
    }

    /**
     * Get the actual PHP type of the value
     */
    public function getValueType(?string $locale = null): string
    {
        $value = $this->getValue($locale);

        return match (true) {
            is_null($value) => 'null',
            is_int($value) => 'integer',
            is_float($value) => 'float',
            is_bool($value) => 'boolean',
            is_string($value) => 'string',
            is_array($value) => 'array',
            is_object($value) => 'object',
            default => 'unknown'
        };
    }

    /**
     * Get the value for a specific locale (unified method)
     * This replaces both getRawValue() and getLocalizedValue()
     */
    public function getValue(?string $locale = null): mixed
    {
        $locale = $locale ?: app()->getLocale();
        $translatableData = $this->getTranslatableValueData();

        if (!$translatableData) {
            // Fall back to current value (non-translatable)
            $value = $this->value;

            // If null, return null directly
            if (is_null($value)) {
                return null;
            }

            // If this is a legacy cast format, extract the actual value
            if (is_array($value) && isset($value['type'], $value['value'])) {
                return $this->castToType($value['type'], $value['value']);
            }

            return $value;
        }

        if (isset($translatableData[$locale])) {
            return $this->extractValueFromLocaleData($translatableData[$locale]);
        }

        // Try fallback locale
        $fallbackLocale = config('app.fallback_locale', 'en');
        if ($locale !== $fallbackLocale && isset($translatableData[$fallbackLocale])) {
            return $this->extractValueFromLocaleData($translatableData[$fallbackLocale]);
        }

        // Return first available translation
        foreach ($translatableData as $localeData) {
            return $this->extractValueFromLocaleData($localeData);
        }

        return null;
    }

    /**
     * Get the raw translatable value data for all locales.
     */
    public function getTranslatableValueData(): ?array
    {
        // Get the raw value directly from the database
        $rawValue = $this->getRawOriginal('value');

        if (is_null($rawValue)) {
            return null;
        }

        try {
            $decoded = json_decode($rawValue, true, 512, JSON_THROW_ON_ERROR);

            if (is_array($decoded) && $this->isTranslatableValueStructure($decoded)) {
                return $decoded;
            }
        } catch (\JsonException) {
            // Not a valid JSON or translatable structure
        }

        return null;
    }

    /**
     * Set translatable value for multiple locales.
     */
    public function setTranslatableValue(array $translations): void
    {
        $this->value = $translations;
    }

    /**
     * Set value for a specific locale.
     */
    public function setValueForLocale(string $locale, mixed $value): void
    {
        $currentData = $this->getTranslatableValueData() ?: [];

        // If current data exists but is not translatable structure, convert it
        if (!empty($currentData) && !$this->isTranslatableValueStructure($currentData)) {
            $currentData = [];
        }

        // The current data already has the structured format, but we need to add
        // the new locale as a raw value that the cast will structure
        $newTranslatableValue = [];

        // Copy existing locales
        foreach ($currentData as $existingLocale => $existingData) {
            if (array_key_exists('value', $existingData)) {
                $newTranslatableValue[$existingLocale] = $existingData['value'];
            }
        }

        // Add the new locale value
        $newTranslatableValue[$locale] = $value;

        $this->setTranslatableValue($newTranslatableValue);
    }

    /**
     * Check if the value field has translations.
     */
    public function hasTranslatableValue(): bool
    {
        return $this->getTranslatableValueData() !== null;
    }

    /**
     * Check if value has translation for specific locale.
     */
    public function hasValueTranslation(string $locale): bool
    {
        $translatableData = $this->getTranslatableValueData();
        return $translatableData && isset($translatableData[$locale]);
    }

    /**
     * Get all available locales for the value field.
     */
    public function getValueLocales(): array
    {
        $translatableData = $this->getTranslatableValueData();
        return $translatableData ? array_keys($translatableData) : [];
    }

    /**
     * Get all value translations as an associative array.
     */
    public function getValueTranslations(): array
    {
        $translatableData = $this->getTranslatableValueData();

        if (!$translatableData) {
            return [];
        }

        $translations = [];
        foreach ($translatableData as $locale => $localeData) {
            $translations[$locale] = $this->extractValueFromLocaleData($localeData);
        }

        return $translations;
    }

    /**
     * Check if this is a numeric feature (for usage counting)
     */
    public function isNumericFeature(?string $locale = null): bool
    {
        return $this->isValueType('int', $locale) || $this->isValueType('float', $locale);
    }

    /**
     * Check if this is a boolean feature (for on/off features)
     */
    public function isBooleanFeature(?string $locale = null): bool
    {
        return $this->isValueType('bool', $locale);
    }

    /**
     * Check if this is an array feature (for multi-value features)
     */
    public function isArrayFeature(?string $locale = null): bool
    {
        return $this->isValueType('array', $locale);
    }

    /**
     * Check if this is an object feature (for complex configuration)
     */
    public function isObjectFeature(?string $locale = null): bool
    {
        return $this->isValueType('object', $locale);
    }

    /**
     * Check if this is a string feature (for text-based features)
     */
    public function isStringFeature(?string $locale = null): bool
    {
        return $this->isValueType('string', $locale);
    }

    /**
     * Check if this is a null feature (for unlimited/disabled features)
     */
    public function isNullFeature(?string $locale = null): bool
    {
        return $this->isValueType('null', $locale);
    }

    /**
     * Get the numeric value (for limits and quotas)
     */
    public function getNumericValue(?string $locale = null): int|float|null
    {
        if ($this->isNumericFeature($locale)) {
            return $this->getValue($locale);
        }

        $value = $this->getValue($locale);

        // Try to cast to numeric if possible
        if (is_string($value) && is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        return null;
    }

    /**
     * Get the boolean value (for feature flags)
     */
    public function getBooleanValue(?string $locale = null): ?bool
    {
        if ($this->isBooleanFeature($locale)) {
            return $this->getValue($locale);
        }

        $value = $this->getValue($locale);

        // Try to cast to boolean if possible
        if (is_string($value)) {
            return match (strtolower($value)) {
                'true', '1', 'yes', 'on' => true,
                'false', '0', 'no', 'off' => false,
                default => null
            };
        }

        if (is_numeric($value)) {
            return (bool) $value;
        }

        return null;
    }

    /**
     * Get the array value (for multi-value features)
     */
    public function getArrayValue(?string $locale = null): ?array
    {
        if ($this->isArrayFeature($locale)) {
            return $this->getValue($locale);
        }

        $value = $this->getValue($locale);

        // Try to convert to array if possible
        if (is_string($value)) {
            // Try to decode JSON
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }

            // Split by comma for simple lists
            return array_map('trim', explode(',', $value));
        }

        // Convert single value to array
        return [$value];
    }

    /**
     * Get the object value (for complex configuration)
     */
    public function getObjectValue(?string $locale = null): ?object
    {
        if ($this->isObjectFeature($locale)) {
            return $this->getValue($locale);
        }

        $value = $this->getValue($locale);

        // Try to convert to object if possible
        if (is_array($value)) {
            return (object) $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value);
            if (is_object($decoded)) {
                return $decoded;
            }
        }

        return (object) ['value' => $value];
    }

    /**
     * Get the string value (for text-based features)
     */
    public function getStringValue(?string $locale = null): string
    {
        if ($this->isStringFeature($locale)) {
            return $this->getValue($locale);
        }

        $value = $this->getValue($locale);

        // Convert to string representation
        return match ($this->getValueType($locale)) {
            'null' => '',
            'boolean' => $value ? 'true' : 'false',
            'array' => json_encode($value),
            'object' => json_encode($value),
            default => (string) $value
        };
    }

    /**
     * Check if the feature represents unlimited access
     */
    public function isUnlimited(?string $locale = null): bool
    {
        return $this->isNullFeature($locale) ||
            ($this->isNumericFeature($locale) && $this->getNumericValue($locale) < 0) ||
            ($this->isStringFeature($locale) && in_array(strtolower($this->getStringValue($locale)), ['unlimited', 'infinite', 'no-limit']));
    }

    /**
     * Check if the feature is enabled/active
     */
    public function isEnabled(?string $locale = null): bool
    {
        $value = $this->getValue($locale);

        return match ($this->getValueType($locale)) {
            'boolean' => $value === true,
            'null' => false, // null typically means disabled unless it's unlimited
            'integer', 'float' => $this->getNumericValue($locale) > 0,
            'string' => !empty($this->getStringValue($locale)) &&
                !in_array(strtolower($this->getStringValue($locale)), ['false', 'disabled', 'off', 'no']),
            'array' => !empty($this->getArrayValue($locale)),
            'object' => !empty((array) $this->getObjectValue($locale)),
            default => false
        };
    }

    /**
     * Compare values for feature comparison
     */
    public function compareValue($otherValue, ?string $locale = null): int
    {
        $thisValue = $this->getValue($locale);

        // Handle null values (unlimited)
        if (is_null($thisValue) && is_null($otherValue)) {
            return 0; // Both unlimited
        }
        if (is_null($thisValue)) {
            return 1; // This is unlimited, other is not
        }
        if (is_null($otherValue)) {
            return -1; // Other is unlimited, this is not
        }

        // Handle numeric values
        if (is_numeric($thisValue) && is_numeric($otherValue)) {
            return $thisValue <=> $otherValue;
        }

        // Handle boolean values
        if (is_bool($thisValue) && is_bool($otherValue)) {
            return $thisValue <=> $otherValue;
        }

        // Handle array values (compare by count)
        if (is_array($thisValue) && is_array($otherValue)) {
            return count($thisValue) <=> count($otherValue);
        }

        // Handle string values
        if (is_string($thisValue) && is_string($otherValue)) {
            return strcmp($thisValue, $otherValue);
        }

        // Handle mixed types - arrays vs other types
        if (is_array($thisValue) && !is_array($otherValue)) {
            return count($thisValue) <=> ($otherValue ?? 0);
        }
        if (!is_array($thisValue) && is_array($otherValue)) {
            return ($thisValue ?? 0) <=> count($otherValue);
        }

        // Handle objects by converting to array first
        if (is_object($thisValue) || is_object($otherValue)) {
            $thisArray = is_object($thisValue) ? (array) $thisValue : [$thisValue];
            $otherArray = is_object($otherValue) ? (array) $otherValue : [$otherValue];
            return count($thisArray) <=> count($otherArray);
        }

        // Default: convert to string and compare (only for scalars)
        if (is_scalar($thisValue) && is_scalar($otherValue)) {
            return strcmp((string) $thisValue, (string) $otherValue);
        }

        // If we can't compare, return 0 (equal)
        return 0;
    }

    /**
     * Check if this feature value is better than another
     */
    public function isBetterThan($otherValue, ?string $locale = null): bool
    {
        return $this->compareValue($otherValue, $locale) > 0;
    }

    /**
     * Check if this feature value is worse than another
     */
    public function isWorseThan($otherValue, ?string $locale = null): bool
    {
        return $this->compareValue($otherValue, $locale) < 0;
    }

    /**
     * Get a human-readable representation of the value
     * This replaces both getDisplayValue() and getLocalizedDisplayValue()
     */
    public function getDisplayValue(?string $locale = null): string
    {
        $value = $this->getValue($locale);

        return match ($this->getValueType($locale)) {
            'null' => 'Unlimited',
            'boolean' => $value ? 'Included' : 'Not included',
            'integer' => number_format($value),
            'float' => number_format($value, 2),
            'array' => count($value) . ' item' . (count($value) !== 1 ? 's' : ''),
            'object' => 'Custom configuration',
            'string' => $value,
            default => 'Unknown'
        };
    }

    /**
     * Validate if the value is appropriate for the feature type
     */
    public function validateValue(?string $locale = null): bool
    {
        // Basic validation - can be extended based on feature key patterns
        $value = $this->getValue($locale);

        // Check for obvious invalid values
        if (is_string($value) && empty(trim($value)) && !$this->isNullFeature($locale)) {
            return false;
        }

        // Check numeric values for negative numbers where inappropriate
        if ($this->isNumericFeature($locale)) {
            $numValue = $this->getNumericValue($locale);
            // Allow negative numbers for unlimited features (-1 pattern)
            if ($numValue < -1) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if the value is translatable structure.
     */
    private function isTranslatableValueStructure(array $decoded): bool
    {
        // Must have at least one entry
        if (empty($decoded)) {
            return false;
        }

        foreach ($decoded as $key => $value) {
            if (!is_string($key) || !is_array($value)) {
                return false;
            }

            if (!array_key_exists('type', $value) || !array_key_exists('value', $value)) {
                return false;
            }

            // Check if key looks like a locale (2-letter code, optionally with country)
            if (!preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', $key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Extract value from locale-specific data structure.
     */
    private function extractValueFromLocaleData(array $localeData): mixed
    {
        if (array_key_exists('type', $localeData) && array_key_exists('value', $localeData)) {
            return $this->castToType($localeData['type'], $localeData['value']);
        }

        return null;
    }

    /**
     * Cast value to the specified type.
     */
    private function castToType(string $type, mixed $value): mixed
    {
        return match ($type) {
            'integer' => (int) $value,
            'float' => (float) $value,
            'boolean' => (bool) $value,
            'string' => (string) $value,
            'array' => is_array($value) ? $value : [],
            'object' => is_object($value) ? $value : (object) [],
            'null' => null,
            default => $value
        };
    }

    // ===== BACKWARD COMPATIBILITY ALIASES =====

    /**
     * Alias for getValue() - for backward compatibility
     * @deprecated Use getValue() instead
     */
    public function getLocalizedValue(?string $locale = null): mixed
    {
        return $this->getValue($locale);
    }

    /**
     * Get the display value for the current locale.
     * This combines the flexible value display logic with translation support.
     * 
     * Note: Uses simplified formatting for backward compatibility with existing tests.
     */
    public function getLocalizedDisplayValue(?string $locale = null): string
    {
        // Use the localized value directly
        $value = $this->getLocalizedValue($locale);

        return match (true) {
            is_null($value) => 'Unlimited',
            is_bool($value) => $value ? 'Included' : 'Not included',
            is_array($value) => implode(', ', $value),
            is_object($value) => json_encode($value),
            default => (string) $value
        };
    }
}
