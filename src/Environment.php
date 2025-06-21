<?php

namespace Bermuda\Config;

use Countable;
use Traversable;
use ArrayIterator;
use IteratorAggregate;
use Bermuda\Stdlib\Arrayable;

/**
 * Environment variable container class
 *
 * Provides access to environment variables that have been loaded by EnvLoader.
 * Supports type conversion, default values, validation of individual variables,
 * and implements standard interfaces for iteration and array conversion.
 *
 * This class is a simple data container and does not handle loading or parsing
 * of .env files. Use EnvLoader for loading environment variables.
 */
final class Environment implements Arrayable, IteratorAggregate, Countable
{
    /**
     * Constructor for Environment
     *
     * @param array $_env Array of environment variables (typically from EnvLoader::load())
     */
    public function __construct(
        public readonly array $_env
    ) {
    }

    /**
     * Get an environment variable value with optional default fallback
     *
     * @param string $key The environment variable name
     * @param mixed $default Default value to return if the variable doesn't exist
     * @return mixed The environment variable value or default
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->_env[$key] ?? $default;
    }

    /**
     * Get environment variable as boolean
     *
     * Converts various string representations to boolean values:
     * - true: "true", "yes", "1", "on", "enabled", "y" (case-insensitive)
     * - false: "false", "no", "0", "off", "disabled", "n" (case-insensitive)
     *
     * @param string $key Environment variable name
     * @param bool $default Default value if environment variable is not set
     * @return bool Boolean value
     */
    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key);
        if ($value === null) return $default;
        if (is_bool($value)) return $value;

        if (is_string($value)) {
            $lower = strtolower(trim($value));
            return match ($lower) {
                'true', '1', 'yes', 'on', 'enabled', 'y' => true,
                'false', '0', 'no', 'off', 'disabled', 'n', '' => false,
                default => (bool) $value
            };
        }

        return (bool) $value;
    }

    /**
     * Get environment variable as integer
     *
     * @param string $key Environment variable name
     * @param int $default Default value if environment variable is not set
     * @return int Integer value
     */
    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->get($key);
        if ($value === null) return $default;

        return filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    /**
     * Get environment variable as float
     *
     * @param string $key Environment variable name
     * @param float $default Default value if environment variable is not set
     * @return float Float value
     */
    public function getFloat(string $key, float $default = 0.0): float
    {
        $value = $this->get($key);
        if ($value === null) return $default;

        return filter_var($value, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    /**
     * Get environment variable as array (comma-separated values)
     *
     * Splits the value by comma and trims each element.
     * Empty string elements are preserved.
     *
     * @param string $key Environment variable name
     * @param array $default Default value if environment variable is not set
     * @return array Array of values
     */
    public function getArray(string $key, array $default = []): array
    {
        $value = $this->get($key);
        if ($value === null) return $default;
        if ($value instanceof Arrayable) return $value->toArray();

        return array_map('trim', explode(',', $value));
    }

    /**
     * Get environment variable as string
     *
     * @param string $key Environment variable name
     * @param string $default Default value if environment variable is not set
     * @return string String value
     */
    public function getString(string $key, string $default = ''): string
    {
        $value = $this->get($key);
        if ($value === null) return $default;

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * Ensure environment variable exists and return it
     *
     * @param string $key Environment variable name
     * @return mixed Environment variable value
     * @throws \OutOfBoundsException If variable doesn't exist or is null
     */
    public function ensureValue(string $key): mixed
    {
        if (!$this->has($key)) {
            throw new \OutOfBoundsException("Required environment variable '$key' is missing");
        }

        $value = $this->_env[$key];

        if ($value === null) {
            throw new \OutOfBoundsException("Required environment variable '$key' is null");
        }

        return $value;
    }

    /**
     * Ensure environment variable exists and return it as string
     *
     * @param string $key Environment variable name
     * @return string Environment variable value as string
     * @throws \OutOfBoundsException If variable doesn't exist or is null
     * @throws \InvalidArgumentException If value cannot be converted to string
     */
    public function ensureString(string $key): string
    {
        $value = $this->ensureValue($key);

        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        throw new \InvalidArgumentException("Environment variable '$key' cannot be converted to string");
    }

    /**
     * Ensure environment variable exists and return it as integer
     *
     * @param string $key Environment variable name
     * @return int Environment variable value as integer
     * @throws \OutOfBoundsException If variable doesn't exist or is null
     * @throws \InvalidArgumentException If value cannot be converted to integer
     */
    public function ensureInt(string $key): int
    {
        $value = $this->ensureValue($key);

        if (is_int($value)) {
            return $value;
        }

        // Use filter_var for validation instead of NumberConverter
        $intValue = filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        if ($intValue !== null) {
            return $intValue;
        }

        // Handle boolean conversion
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        // Handle string numeric values manually for edge cases
        if (is_string($value)) {
            $trimmed = trim($value);
            if (ctype_digit($trimmed) || (str_starts_with($trimmed, '-') && ctype_digit(substr($trimmed, 1)))) {
                $converted = (int) $trimmed;
                if ((string) $converted === $trimmed) {
                    return $converted;
                }
            }
        }

        throw new \InvalidArgumentException("Environment variable '$key' cannot be converted to integer");
    }

    /**
     * Ensure environment variable exists and return it as float
     *
     * @param string $key Environment variable name
     * @return float Environment variable value as float
     * @throws \OutOfBoundsException If variable doesn't exist or is null
     * @throws \InvalidArgumentException If value cannot be converted to float
     */
    public function ensureFloat(string $key): float
    {
        $value = $this->ensureValue($key);

        if (is_float($value)) {
            return $value;
        }

        if (is_int($value)) {
            return (float) $value;
        }

        // Use filter_var for validation
        $floatValue = filter_var($value, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
        if ($floatValue !== null) {
            return $floatValue;
        }

        // Handle boolean conversion
        if (is_bool($value)) {
            return $value ? 1.0 : 0.0;
        }

        throw new \InvalidArgumentException("Environment variable '$key' cannot be converted to float");
    }

    /**
     * Ensure environment variable exists and return it as boolean
     *
     * @param string $key Environment variable name
     * @return bool Environment variable value as boolean
     * @throws \OutOfBoundsException If variable doesn't exist or is null
     */
    public function ensureBool(string $key): bool
    {
        $value = $this->ensureValue($key);

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $lower = strtolower(trim($value));
            return match ($lower) {
                'true', '1', 'yes', 'on', 'enabled', 'y' => true,
                'false', '0', 'no', 'off', 'disabled', 'n', '' => false,
                default => (bool) $value
            };
        }

        return (bool) $value;
    }

    /**
     * Ensure environment variable exists and return it as array
     *
     * @param string $key Environment variable name
     * @return array Environment variable value as array
     * @throws \OutOfBoundsException If variable doesn't exist or is null
     */
    public function ensureArray(string $key): array
    {
        $value = $this->ensureValue($key);

        if (is_array($value)) {
            return $value;
        }

        if ($value instanceof Arrayable) {
            return $value->toArray();
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return [];
            }
            return array_map('trim', explode(',', $trimmed));
        }

        return [$value];
    }

    /**
     * Check if an environment variable exists
     *
     * @param string $key The environment variable name to check
     * @return bool True if the variable exists (even if empty), false otherwise
     */
    public function has(string $key): bool
    {
        return isset($this->_env[$key]) || array_key_exists($key, $this->_env);
    }

    /**
     * Get all environment variables as an array
     * Implementation of Arrayable interface
     *
     * @return array All environment variables
     */
    public function toArray(): array
    {
        return $this->_env;
    }

    /**
     * Get an iterator for iterating over all environment variables
     * Implementation of IteratorAggregate interface
     *
     * @return Traversable An iterator for the environment variables
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->_env);
    }

    /**
     * Get count of environment variables
     *
     * @return int Number of environment variables
     */
    public function count(): int
    {
        return count($this->_env);
    }

    /**
     * Check if environment is empty
     *
     * @return bool True if no environment variables are set
     */
    public function isEmpty(): bool
    {
        return empty($this->_env);
    }

    /**
     * Get environment variable keys
     *
     * @return array Array of environment variable names
     */
    public function keys(): array
    {
        return array_keys($this->_env);
    }

    /**
     * Get environment variable values
     *
     * @return array Array of environment variable values
     */
    public function values(): array
    {
        return array_values($this->_env);
    }

    /**
     * Filter environment variables by key pattern
     *
     * @param string $pattern Pattern to match (supports wildcards)
     * @return array Filtered environment variables
     */
    public function filter(string $pattern): array
    {
        return array_filter(
            $this->_env,
            fn($key) => fnmatch($pattern, $key),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Get environment variables with a specific prefix
     *
     * @param string $prefix Prefix to search for
     * @param bool $removePrefix Whether to remove the prefix from keys
     * @return array Environment variables with the specified prefix
     */
    public function getByPrefix(string $prefix, bool $removePrefix = false): array
    {
        $result = [];
        $prefixLength = strlen($prefix);

        foreach ($this->_env as $key => $value) {
            if (str_starts_with($key, $prefix)) {
                $resultKey = $removePrefix ? substr($key, $prefixLength) : $key;
                $result[$resultKey] = $value;
            }
        }

        return $result;
    }
}