<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Cast for flexible value types in plan features.
 * 
 * Handles conversion between database JSON storage and PHP native types:
 * - integers, floats, booleans, strings, arrays, objects, null
 * 
 * Storage format:
 * {
 *   "type": "integer|float|boolean|string|array|object|null",
 *   "value": mixed
 * }
 */
class FlexibleValueCast implements CastsAttributes
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

            if (!is_array($decoded) || !isset($decoded['type'], $decoded['value'])) {
                // Invalid format, treat as legacy string
                return $this->castLegacyValue($value);
            }

            return $this->castToType($decoded['type'], $decoded['value']);
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

        $type = $this->getValueType($value);

        return json_encode([
            'type' => $type,
            'value' => $value
        ], JSON_UNESCAPED_UNICODE);
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
        // Try to intelligently cast legacy string values

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
            // Check if it's an integer
            if (ctype_digit($value) || (str_starts_with($value, '-') && ctype_digit(substr($value, 1)))) {
                return (int) $value;
            }
            // It's a float
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