<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Traits;

/**
 * Trait for handling flexible value operations
 * 
 * Provides methods for working with flexible value types in plan features.
 * Used with FlexibleValueCast to handle different data types safely.
 */
trait HandlesFlexibleValues
{
    /**
     * Get the value as a specific type
     */
    public function getValueAs(string $type): mixed
    {
        $value = $this->getRawValue();

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
    public function isValueType(string $type): bool
    {
        $value = $this->getRawValue();

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
    public function getValueType(): string
    {
        $value = $this->getRawValue();

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
     * Get the raw value, handling both casted and uncasted scenarios
     */
    private function getRawValue(): mixed
    {
        $value = $this->value;

        // If null, return null directly
        if (is_null($value)) {
            return null;
        }

        // If this is a FlexibleValueCast array format, extract the actual value
        if (is_array($value) && isset($value['type'], $value['value'])) {
            return $this->castFlexibleValue($value['type'], $value['value']);
        }

        return $value;
    }

    /**
     * Cast value based on flexible cast format
     */
    private function castFlexibleValue(string $type, mixed $value): mixed
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
     * Check if this is a numeric feature (for usage counting)
     */
    public function isNumericFeature(): bool
    {
        return $this->isValueType('int') || $this->isValueType('float');
    }

    /**
     * Check if this is a boolean feature (for on/off features)
     */
    public function isBooleanFeature(): bool
    {
        return $this->isValueType('bool');
    }

    /**
     * Check if this is an array feature (for multi-value features)
     */
    public function isArrayFeature(): bool
    {
        return $this->isValueType('array');
    }

    /**
     * Check if this is an object feature (for complex configuration)
     */
    public function isObjectFeature(): bool
    {
        return $this->isValueType('object');
    }

    /**
     * Check if this is a string feature (for text-based features)
     */
    public function isStringFeature(): bool
    {
        return $this->isValueType('string');
    }

    /**
     * Check if this is a null feature (for unlimited/disabled features)
     */
    public function isNullFeature(): bool
    {
        return $this->isValueType('null');
    }

    /**
     * Get the numeric value (for limits and quotas)
     */
    public function getNumericValue(): int|float|null
    {
        if ($this->isNumericFeature()) {
            return $this->getRawValue();
        }

        $value = $this->getRawValue();

        // Try to cast to numeric if possible
        if (is_string($value) && is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        return null;
    }

    /**
     * Get the boolean value (for feature flags)
     */
    public function getBooleanValue(): ?bool
    {
        if ($this->isBooleanFeature()) {
            return $this->getRawValue();
        }

        $value = $this->getRawValue();

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
    public function getArrayValue(): ?array
    {
        if ($this->isArrayFeature()) {
            return $this->getRawValue();
        }

        $value = $this->getRawValue();

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
    public function getObjectValue(): ?object
    {
        if ($this->isObjectFeature()) {
            return $this->getRawValue();
        }

        $value = $this->getRawValue();

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
    public function getStringValue(): string
    {
        if ($this->isStringFeature()) {
            return $this->getRawValue();
        }

        $value = $this->getRawValue();

        // Convert to string representation
        return match ($this->getValueType()) {
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
    public function isUnlimited(): bool
    {
        return $this->isNullFeature() ||
            ($this->isNumericFeature() && $this->getNumericValue() < 0) ||
            ($this->isStringFeature() && in_array(strtolower($this->getStringValue()), ['unlimited', 'infinite', 'no-limit']));
    }

    /**
     * Check if the feature is enabled/active
     */
    public function isEnabled(): bool
    {
        $value = $this->getRawValue();

        return match ($this->getValueType()) {
            'boolean' => $value === true,
            'null' => false, // null typically means disabled unless it's unlimited
            'integer', 'float' => $this->getNumericValue() > 0,
            'string' => !empty($this->getStringValue()) &&
                !in_array(strtolower($this->getStringValue()), ['false', 'disabled', 'off', 'no']),
            'array' => !empty($this->getArrayValue()),
            'object' => !empty((array) $this->getObjectValue()),
            default => false
        };
    }

    /**
     * Compare values for feature comparison
     */
    public function compareValue($otherValue): int
    {
        $thisValue = $this->getRawValue();

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
    public function isBetterThan($otherValue): bool
    {
        return $this->compareValue($otherValue) > 0;
    }

    /**
     * Check if this feature value is worse than another
     */
    public function isWorseThan($otherValue): bool
    {
        return $this->compareValue($otherValue) < 0;
    }

    /**
     * Get a human-readable representation of the value
     */
    public function getDisplayValue(): string
    {
        $value = $this->getRawValue();

        return match ($this->getValueType()) {
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
    public function validateValue(): bool
    {
        // Basic validation - can be extended based on feature key patterns
        $value = $this->getRawValue();

        // Check for obvious invalid values
        if (is_string($value) && empty(trim($value)) && !$this->isNullFeature()) {
            return false;
        }

        // Check numeric values for negative numbers where inappropriate
        if ($this->isNumericFeature()) {
            $numValue = $this->getNumericValue();
            // Allow negative numbers for unlimited features (-1 pattern)
            if ($numValue < -1) {
                return false;
            }
        }

        return true;
    }
}