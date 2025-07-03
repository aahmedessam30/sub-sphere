<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Cast for translatable flexible value types in plan features.
 * 
 * Combines translation support with flexible value type handling.
 * 
 * Storage format for translatable values:
 * {
 *   "en": {"type": "string", "value": "Premium Support"},
 *   "ar": {"type": "string", "value": "دعم مميز"},
 *   "fr": {"type": "string", "value": "Support Premium"}
 * }
 * 
 * Storage format for non-translatable values (backwards compatibility):
 * {
 *   "type": "integer|float|boolean|string|array|object|null",
 *   "value": mixed
 * }
 */
class TranslatableFlexibleValueCast implements CastsAttributes
{
    /**
     * Cast the given value from database to PHP native type.
     *
     * @param Model $model
     * @param string $key
     * @param mixed $value
     * @param array $attributes
     * @return mixed
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if (is_null($value)) {
            return null;
        }

        // Handle legacy string values (backwards compatibility)
        if (!$this->isJsonString($value)) {
            return $this->castLegacyValue($value);
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($decoded)) {
                return $this->castLegacyValue($value);
            }

            // Check if this is a translatable value structure
            if ($this->isTranslatableStructure($decoded)) {
                return $this->handleTranslatableValue($decoded);
            }

            // Check if this is a single flexible value structure
            if (array_key_exists('type', $decoded) && array_key_exists('value', $decoded)) {
                return $this->castToType($decoded['type'], $decoded['value']);
            }

            // Invalid format, treat as legacy
            return $this->castLegacyValue($value);
        } catch (\JsonException) {
            // Invalid JSON, treat as legacy string
            return $this->castLegacyValue($value);
        }
    }

    /**
     * Prepare the given value for storage in database.
     *
     * @param Model $model
     * @param string $key
     * @param mixed $value
     * @param array $attributes
     * @return string|null
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (is_null($value)) {
            return null;
        }

        // If the value is an array with locale keys, treat as translatable
        if (is_array($value) && $this->hasLocaleKeys($value)) {
            $translatableData = [];

            foreach ($value as $locale => $localeValue) {
                $type = $this->getValueType($localeValue);
                $translatableData[$locale] = [
                    'type' => $type,
                    'value' => $localeValue
                ];
            }

            return json_encode($translatableData, JSON_UNESCAPED_UNICODE);
        }

        // Single value - store as flexible value
        $type = $this->getValueType($value);
        return json_encode([
            'type' => $type,
            'value' => $value
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Handle translatable value based on current locale.
     */
    private function handleTranslatableValue(array $decoded): mixed
    {
        $locale = app()->getLocale();
        $fallbackLocale = config('app.fallback_locale', 'en');

        // Try current locale first
        if (isset($decoded[$locale])) {
            return $this->extractValueFromLocaleData($decoded[$locale]);
        }

        // Try fallback locale
        if (isset($decoded[$fallbackLocale])) {
            return $this->extractValueFromLocaleData($decoded[$fallbackLocale]);
        }

        // Return first available translation
        foreach ($decoded as $localeData) {
            return $this->extractValueFromLocaleData($localeData);
        }

        return null;
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
     * Check if the decoded structure represents translatable values.
     */
    private function isTranslatableStructure(array $decoded): bool
    {
        // Must have at least one entry
        if (empty($decoded)) {
            return false;
        }

        // Check if all top-level keys look like locale codes and contain type/value structure
        foreach ($decoded as $key => $value) {
            if (!is_string($key) || !is_array($value)) {
                return false;
            }

            // Check if it has the expected structure
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
     * Check if array has locale-like keys.
     */
    private function hasLocaleKeys(array $value): bool
    {
        $localePattern = '/^[a-z]{2}(-[A-Z]{2})?$/';

        foreach (array_keys($value) as $key) {
            if (!is_string($key) || !preg_match($localePattern, $key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine the type of the given value.
     */
    private function getValueType(mixed $value): string
    {
        return match (true) {
            is_int($value) => 'integer',
            is_float($value) => 'float',
            is_bool($value) => 'boolean',
            is_string($value) => 'string',
            is_array($value) => 'array',
            is_object($value) => 'object',
            default => 'null'
        };
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

    /**
     * Handle legacy string values for backwards compatibility.
     */
    private function castLegacyValue(string $value): mixed
    {
        // Check for null-like values
        if (in_array(strtolower($value), ['null', 'nil', ''], true)) {
            return null;
        }

        // Check for boolean values
        if (in_array(strtolower($value), ['true', '1', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array(strtolower($value), ['false', '0', 'no', 'off'], true)) {
            return false;
        }

        // Check for numeric values
        if (is_numeric($value)) {
            if (ctype_digit($value) || (str_starts_with($value, '-') && ctype_digit(substr($value, 1)))) {
                return (int) $value;
            }
            return (float) $value;
        }

        // Check for JSON arrays/objects
        if ($this->isJsonString($value)) {
            try {
                return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                // Fall through to return as string
            }
        }

        // Return as string by default
        return $value;
    }

    /**
     * Check if the given value is a valid JSON string.
     */
    private function isJsonString(string $value): bool
    {
        if (empty($value)) {
            return false;
        }

        $firstChar = $value[0];
        return in_array($firstChar, ['{', '[', '"'], true);
    }
}
