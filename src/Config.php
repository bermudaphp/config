<?php

namespace Bermuda\Config;

use Bermuda\Stdlib\Arrayable;
use Bermuda\ContainerProviderInterface;
use Bermuda\ContainerAware;
use Bermuda\Stdlib\NumberConverter;
use Psr\Container\ContainerInterface;

/**
 * Immutable configuration class with container support
 *
 * Provides access to configuration data with array-like interface,
 * iteration and counting capabilities. The class is immutable after creation.
 * Supports callable values with configurable behavior flags and container
 * injection for service resolution. Callables receive the Config instance
 * as parameter and can access the container via $config->container property.
 */
final class Config implements \Countable, \IteratorAggregate, \ArrayAccess, Arrayable, ContainerProviderInterface
{
    use ContainerAware;

    /** Call callable configuration values */
    public const int CALL_VALUES = 1;

    /** Call callable default values */
    public const int CALL_DEFAULTS = 2;

    /** Call all callable values (both config and defaults) */
    public const int CALL_ALL = self::CALL_VALUES | self::CALL_DEFAULTS;

    /** Disable caching of callable results */
    public const int NO_CACHE = 4;

    /** No callable processing */
    public const int CALL_NONE = 0;

    /**
     * Constructor for Config
     *
     * @param array $data Configuration data array
     * @param Environment|null $environment Environment variables handler (optional)
     * @param ContainerInterface|null $container Dependency injection container (optional)
     */
    public function __construct(
        public readonly array $data,
        public readonly ?Environment $environment = null,
        ?ContainerInterface $container = null
    ) {
        if ($container !== null) {
            $this->setContainer($container);
        }
    }

    /** @var array Cache for resolved callable values */
    private array $callableCache = [];

    /**
     * Create configuration instance
     *
     * @param array $data Configuration data
     * @param Environment|null $environment Environment variables handler (optional)
     * @param ContainerInterface|null $container Dependency injection container (optional)
     * @return Config Configuration instance
     */
    public static function create(array $data, ?Environment $environment = null, ?ContainerInterface $container = null): Config
    {
        return new Config($data, $environment, $container);
    }

    /**
     * Get iterator for traversing configuration data
     * Implementation of IteratorAggregate interface
     *
     * @return \Generator Generator for iterating over data
     */
    public function getIterator(): \Generator
    {
        yield from $this->data;
    }

    /**
     * Magic method to get value by key
     *
     * @param int|string|float $name Configuration key
     * @return Config|mixed Configuration value
     */
    public function __get(int|string|float $name): mixed
    {
        return $this->offsetGet($name);
    }

    /**
     * Magic method to set value (throws exception as config is immutable)
     *
     * @param int|string|float $name Configuration key
     * @param mixed $value Value to set
     * @throws \RuntimeException Since configuration is immutable
     */
    public function __set(int|string|float $name, mixed $value)
    {
        $this->offsetSet($name, $value);
    }

    /**
     * Get value by offset (ArrayAccess implementation)
     *
     * @param mixed $offset Key/offset
     * @return Config|mixed Configuration value
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset, null, self::CALL_VALUES);
    }

    /**
     * Get configuration value with optional default fallback
     *
     * Uses dot notation by default. To access literal keys containing dots,
     * wrap the key with Key::literal() or use the key() helper function.
     *
     * @param int|string|float|Key $offset Configuration key (supports dot notation) or Key wrapper
     * @param mixed $default Default value
     * @param int $flags Bitwise flags controlling callable behavior
     * @return self|null|mixed Configuration value or default
     */
    public function get(int|string|float|Key $offset, mixed $default = null, int $flags = self::CALL_VALUES): mixed
    {
        if ($offset instanceof Key) {
            // Handle literal key
            $exists = $this->offsetExists($offset->value);
            if ($exists) {
                $value = $this->data[$offset->value];
                return ($flags & self::CALL_VALUES) ? $this->resolveCallable($value, $offset->value, $flags) : $value;
            } else {
                return ($flags & self::CALL_DEFAULTS) ? $this->resolveCallable($default, "default:{$offset->value}", $flags) : $default;
            }
        }

        // Handle dot notation by default
        $key = (string)$offset;

        if (empty($key)) {
            return ($flags & self::CALL_DEFAULTS) ? $this->resolveCallable($default, "default:$key", $flags) : $default;
        }

        // If key exists directly (no dot notation needed)
        if ($this->offsetExists($key)) {
            $value = $this->data[$key];
            return ($flags & self::CALL_VALUES) ? $this->resolveCallable($value, $key, $flags) : $value;
        }

        // Split the key by dots and traverse the array
        $keys = explode('.', $key);
        $value = $this->data;
        $found = true;

        foreach ($keys as $segment) {
            if (!is_array($value) || !(isset($value[$segment]) || array_key_exists($segment, $value))) {
                $found = false;
                break;
            }
            $value = $value[$segment];
        }

        if ($found) {
            return ($flags & self::CALL_VALUES) ? $this->resolveCallable($value, $key, $flags) : $value;
        }

        return ($flags & self::CALL_DEFAULTS) ? $this->resolveCallable($default, "default:$key", $flags) : $default;
    }

    /**
     * Check if configuration key exists using dot notation
     *
     * Uses dot notation by default. To check literal keys containing dots,
     * wrap the key with Key::literal() or use the key() helper function.
     *
     * @param string|Key $key Configuration key (supports dot notation) or Key wrapper
     * @return bool True if key exists, false otherwise
     */
    public function has(string|Key $key): bool
    {
        if ($key instanceof Key) {
            // Handle literal key
            return $this->offsetExists($key->value);
        }

        $keyString = (string)$key;

        if (empty($keyString)) {
            return false;
        }

        // If key exists directly (no dot notation needed)
        if ($this->offsetExists($keyString)) {
            return true;
        }

        // Split the key by dots and traverse the array
        $keys = explode('.', $keyString);
        $value = $this->data;

        foreach ($keys as $segment) {
            if (!is_array($value) || !(isset($value[$segment]) || array_key_exists($segment, $value))) {
                return false;
            }
            $value = $value[$segment];
        }

        return true;
    }

    /**
     * Resolve callable value with caching support
     *
     * @param mixed $value Value to potentially resolve as callable
     * @param string $cacheKey Cache key for storing resolved value
     * @param int $flags Bitwise flags controlling caching behavior
     * @return mixed Resolved value (called if callable, original otherwise)
     */
    private function resolveCallable(mixed $value, string $cacheKey, int $flags): mixed
    {
        // If value is not callable, return as-is
        if (!is_callable($value)) {
            return $value;
        }

        $cacheEnabled = !($flags & self::NO_CACHE);

        // Check if we should use cached value
        if ($cacheEnabled && isset($this->callableCache[$cacheKey])) {
            return $this->callableCache[$cacheKey];
        }

        // Call the callable with Config instance as parameter
        // Container is accessible through $config->container if needed
        $result = $value($this);

        // Cache the result if caching is enabled
        if ($cacheEnabled) {
            $this->callableCache[$cacheKey] = $result;
        }

        return $result;
    }

    /**
     * Set value by offset (ArrayAccess implementation)
     *
     * @param mixed $offset Key/offset
     * @param mixed $value Value
     * @throws \RuntimeException Since configuration is immutable
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \RuntimeException('Config is immutable');
    }

    /**
     * Convert configuration to array (Arrayable implementation)
     *
     * @return array Configuration data array
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Check if key exists (magic method)
     *
     * @param string|int|float $offset Key to check
     * @return bool True if key exists
     */
    public function __isset(string|int|float $offset): bool
    {
        return $this->offsetExists($offset);
    }

    /**
     * Check if offset exists (ArrayAccess implementation)
     *
     * @param mixed $offset Key/offset to check
     * @return bool True if offset exists
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->data[$offset]) || array_key_exists($offset, $this->data);
    }

    /**
     * Unset element (magic method)
     *
     * @param string|int|float $name Key to unset
     * @throws \RuntimeException Since configuration is immutable
     */
    public function __unset(string|int|float $name): void
    {
        $this->offsetUnset($name);
    }

    /**
     * Unset element by offset (ArrayAccess implementation)
     *
     * @param mixed $offset Key/offset to unset
     * @throws \RuntimeException Since configuration is immutable
     */
    public function offsetUnset(mixed $offset): void
    {
        throw new \RuntimeException('Config is immutable');
    }

    /**
     * Get configuration value as integer with optional default fallback
     *
     * Attempts to convert the configuration value to an integer.
     * Supports string numeric values, floats (truncated), and booleans.
     * Uses dot notation by default. For literal keys with dots, use Key::literal().
     *
     * @param int|string|float|Key $offset Configuration key (supports dot notation) or Key wrapper
     * @param int $default Default integer value if key doesn't exist or conversion fails
     * @param int $flags Bitwise flags controlling callable behavior
     * @return int Configuration value converted to integer or default
     */
    public function getInt(int|string|float|Key $offset, int $default = 0, int $flags = self::CALL_VALUES): int
    {
        $value = $this->get($offset, $default, $flags);

        if (is_int($value)) {
            return $value;
        }

        if (NumberConverter::isNumeric($value)) {
            return (int) NumberConverter::convertValue($value);
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        return $default;
    }

    /**
     * Get configuration value as float with optional default fallback
     *
     * Attempts to convert the configuration value to a float.
     * Supports string numeric values, integers, and booleans.
     * Uses dot notation by default. For literal keys with dots, use Key::literal().
     *
     * @param int|string|float|Key $offset Configuration key (supports dot notation) or Key wrapper
     * @param float $default Default float value if key doesn't exist or conversion fails
     * @param int $flags Bitwise flags controlling callable behavior
     * @return float Configuration value converted to float or default
     */
    public function getFloat(int|string|float|Key $offset, float $default = 0.0, int $flags = self::CALL_VALUES): float
    {
        $value = $this->get($offset, $default, $flags);

        if (is_float($value)) {
            return $value;
        }

        if (NumberConverter::isNumeric($value)) {
            return (float) NumberConverter::convertValue($value);
        }

        if (is_bool($value)) {
            return $value ? 1.0 : 0.0;
        }

        return $default;
    }

    /**
     * Get configuration value as boolean with optional default fallback
     *
     * Converts various value types to boolean following PHP's truthiness rules
     * with special handling for common string representations.
     * Uses dot notation by default. For literal keys with dots, use Key::literal().
     *
     * @param int|string|float|Key $offset Configuration key (supports dot notation) or Key wrapper
     * @param bool $default Default boolean value if key doesn't exist
     * @param int $flags Bitwise flags controlling callable behavior
     * @return bool Configuration value converted to boolean or default
     */
    public function getBool(int|string|float|Key $offset, bool $default = false, int $flags = self::CALL_VALUES): bool
    {
        $value = $this->get($offset, $default, $flags);

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
     * Get configuration value as array with optional default fallback
     *
     * Converts various value types to arrays. Strings are split by comma,
     * other scalar values are wrapped in an array, and existing arrays
     * are returned as-is.
     * Uses dot notation by default. For literal keys with dots, use Key::literal().
     *
     * @param int|string|float|Key $offset Configuration key (supports dot notation) or Key wrapper
     * @param array $default Default array value if key doesn't exist
     * @param int $flags Bitwise flags controlling callable behavior
     * @return array Configuration value converted to array or default
     */
    public function getArray(int|string|float|Key $offset, array $default = [], int $flags = self::CALL_VALUES): array
    {
        $value = $this->get($offset, $default, $flags);

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return [];
            }
            return array_map('trim', explode(',', $trimmed));
        }

        if ($value === null) {
            return $default;
        }

        return [$value];
    }

    /**
     * Get configuration value as string with optional default fallback
     *
     * Converts various value types to strings.
     * Uses dot notation by default. For literal keys with dots, use Key::literal().
     *
     * @param int|string|float|Key $offset Configuration key (supports dot notation) or Key wrapper
     * @param string $default Default string value if key doesn't exist
     * @param int $flags Bitwise flags controlling callable behavior
     * @return string Configuration value converted to string or default
     */
    public function getString(int|string|float|Key $offset, string $default = '', int $flags = self::CALL_VALUES): string
    {
        $value = $this->get($offset, $default, $flags);

        if ($value === null) {
            return $default;
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return $default;
    }

    /**
     * Ensure configuration value exists and return it
     *
     * Uses dot notation by default. For literal keys with dots, use Key::literal().
     *
     * @param int|string|float|Key $key Configuration key (supports dot notation) or Key wrapper
     * @param int $flags Bitwise flags controlling callable behavior
     * @return mixed Configuration value
     * @throws \OutOfBoundsException If value doesn't exist or is null
     */
    public function ensureValue(int|string|float|Key $key, int $flags = self::CALL_VALUES): mixed
    {
        $keyExists = $this->has($key);

        if (!$keyExists) {
            $keyString = $key instanceof Key ? $key->value : (string)$key;
            throw new \OutOfBoundsException("Required configuration key '$keyString' is missing");
        }

        $value = $this->get($key, null, $flags);

        if ($value === null) {
            $keyString = $key instanceof Key ? $key->value : (string)$key;
            throw new \OutOfBoundsException("Required configuration key '$keyString' is null");
        }

        return $value;
    }

    /**
     * Ensure configuration value exists and return it as string
     *
     * Uses dot notation by default. For literal keys with dots, use Key::literal().
     *
     * @param int|string|float|Key $key Configuration key (supports dot notation) or Key wrapper
     * @param int $flags Bitwise flags controlling callable behavior
     * @return string Configuration value as string
     * @throws \OutOfBoundsException If value doesn't exist or is null
     * @throws \InvalidArgumentException If value cannot be converted to string
     */
    public function ensureString(int|string|float|Key $key, int $flags = self::CALL_VALUES): string
    {
        $value = $this->ensureValue($key, $flags);

        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        $keyString = $key instanceof Key ? $key->value : (string)$key;
        throw new \InvalidArgumentException("Configuration key '$keyString' cannot be converted to string");
    }

    /**
     * Ensure configuration value exists and return it as integer
     *
     * Uses dot notation by default. For literal keys with dots, use Key::literal().
     *
     * @param int|string|float|Key $key Configuration key (supports dot notation) or Key wrapper
     * @param int $flags Bitwise flags controlling callable behavior
     * @return int Configuration value as integer
     * @throws \OutOfBoundsException If value doesn't exist or is null
     * @throws \InvalidArgumentException If value cannot be converted to integer
     */
    public function ensureInt(int|string|float|Key $key, int $flags = self::CALL_VALUES): int
    {
        $value = $this->ensureValue($key, $flags);

        if (is_int($value)) {
            return $value;
        }

        if (NumberConverter::isNumeric($value)) {
            return (int) NumberConverter::convertValue($value);
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        $keyString = $key instanceof Key ? $key->value : (string)$key;
        throw new \InvalidArgumentException("Configuration key '$keyString' cannot be converted to integer");
    }

    /**
     * Ensure configuration value exists and return it as float
     *
     * Uses dot notation by default. For literal keys with dots, use Key::literal().
     *
     * @param int|string|float|Key $key Configuration key (supports dot notation) or Key wrapper
     * @param int $flags Bitwise flags controlling callable behavior
     * @return float Configuration value as float
     * @throws \OutOfBoundsException If value doesn't exist or is null
     * @throws \InvalidArgumentException If value cannot be converted to float
     */
    public function ensureFloat(int|string|float|Key $key, int $flags = self::CALL_VALUES): float
    {
        $value = $this->ensureValue($key, $flags);

        if (is_float($value)) {
            return $value;
        }

        if (NumberConverter::isNumeric($value)) {
            return (float) NumberConverter::convertValue($value);
        }

        if (is_bool($value)) {
            return $value ? 1.0 : 0.0;
        }

        $keyString = $key instanceof Key ? $key->value : (string)$key;
        throw new \InvalidArgumentException("Configuration key '$keyString' cannot be converted to float");
    }

    /**
     * Ensure configuration value exists and return it as boolean
     *
     * Uses dot notation by default. For literal keys with dots, use Key::literal().
     *
     * @param int|string|float|Key $key Configuration key (supports dot notation) or Key wrapper
     * @param int $flags Bitwise flags controlling callable behavior
     * @return bool Configuration value as boolean
     * @throws \OutOfBoundsException If value doesn't exist or is null
     */
    public function ensureBool(int|string|float|Key $key, int $flags = self::CALL_VALUES): bool
    {
        $value = $this->ensureValue($key, $flags);

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
     * Ensure configuration value exists and return it as array
     *
     * Uses dot notation by default. For literal keys with dots, use Key::literal().
     *
     * @param int|string|float|Key $key Configuration key (supports dot notation) or Key wrapper
     * @param int $flags Bitwise flags controlling callable behavior
     * @return array Configuration value as array
     * @throws \OutOfBoundsException If value doesn't exist or is null
     */
    public function ensureArray(int|string|float|Key $key, int $flags = self::CALL_VALUES): array
    {
        $value = $this->ensureValue($key, $flags);

        if (is_array($value)) {
            return $value;
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
     * Count elements in configuration (Countable implementation)
     *
     * @param bool $recursive Use recursive counting
     * @return int Number of elements
     */
    public function count(bool $recursive = false): int
    {
        return count($this->data, $recursive ? COUNT_RECURSIVE : COUNT_NORMAL);
    }

    /**
     * Create new Config with only specified keys
     *
     * Returns a new Config instance containing only the specified keys.
     * Supports both top-level keys and dot notation for nested values.
     * Use Key::literal() or key() helper for literal keys containing dots.
     *
     * @param string|array|Key $keys Key, array of keys, or Key wrapper to include
     * @param string|Key ...$additionalKeys Additional keys to include
     * @return Config New Config instance with only specified keys
     */
    public function only(string|array|Key $keys, string|Key ...$additionalKeys): Config
    {
        $keysArray = is_array($keys) ? $keys : [$keys, ...$additionalKeys];
        $filteredData = [];

        foreach ($keysArray as $key) {
            if ($key instanceof Key) {
                // Handle literal key
                if ($this->offsetExists($key->value)) {
                    $filteredData[$key->value] = $this->data[$key->value];
                }
            } elseif (str_contains($key, '.')) {
                // Handle dot notation
                $value = $this->get($key);
                if ($value !== null) {
                    $this->setNestedValue($filteredData, $key, $value);
                }
            } else {
                // Handle top-level key
                if ($this->offsetExists($key)) {
                    $filteredData[$key] = $this->data[$key];
                }
            }
        }

        return new Config($filteredData, $this->environment, $this->container);
    }

    /**
     * Create new Config without specified keys
     *
     * Returns a new Config instance excluding the specified keys.
     * Supports both top-level keys and dot notation for nested values.
     * Use Key::literal() or key() helper for literal keys containing dots.
     *
     * @param string|array|Key $keys Key, array of keys, or Key wrapper to exclude
     * @param string|Key ...$additionalKeys Additional keys to exclude
     * @return Config New Config instance without specified keys
     */
    public function except(string|array|Key $keys, string|Key ...$additionalKeys): Config
    {
        $keysArray = is_array($keys) ? $keys : [$keys, ...$additionalKeys];
        $filteredData = $this->data;

        foreach ($keysArray as $key) {
            if ($key instanceof Key) {
                // Handle literal key
                unset($filteredData[$key->value]);
            } elseif (str_contains($key, '.')) {
                // Handle dot notation - remove nested value
                $this->unsetNestedValue($filteredData, $key);
            } else {
                // Handle top-level key
                unset($filteredData[$key]);
            }
        }

        return new Config($filteredData, $this->environment, $this->container);
    }

    /**
     * Set nested value using dot notation
     *
     * @param array $array Target array
     * @param string $key Dot-separated key
     * @param mixed $value Value to set
     */
    private function setNestedValue(array &$array, string $key, mixed $value): void
    {
        if ($value === null) {
            return; // Don't set null values
        }

        $keys = explode('.', $key);
        $current = &$array;

        foreach ($keys as $k) {
            if (!isset($current[$k]) || !is_array($current[$k])) {
                $current[$k] = [];
            }
            $current = &$current[$k];
        }

        $current = $value;
    }

    /**
     * Unset nested value using dot notation
     *
     * @param array $array Target array
     * @param string $key Dot-separated key
     */
    private function unsetNestedValue(array &$array, string $key): void
    {
        $keys = explode('.', $key);
        $lastKey = array_pop($keys);
        $current = &$array;

        // Navigate to parent
        foreach ($keys as $k) {
            if (!isset($current[$k]) || !is_array($current[$k])) {
                return; // Path doesn't exist
            }
            $current = &$current[$k];
        }

        // Remove the final key
        unset($current[$lastKey]);

        // Clean up empty parent arrays
        $this->cleanupEmptyArrays($array, implode('.', $keys));
    }

    /**
     * Remove empty arrays after unsetting nested values
     *
     * @param array $array Target array
     * @param string $path Path to check for empty arrays
     */
    private function cleanupEmptyArrays(array &$array, string $path): void
    {
        if (empty($path)) {
            return;
        }

        $keys = explode('.', $path);
        $current = &$array;

        // Navigate to the parent of the removed key
        foreach ($keys as $k) {
            if (!isset($current[$k])) {
                return;
            }
            $current = &$current[$k];
        }

        // If current array is empty, remove it and check parent
        if (empty($current)) {
            $parentPath = implode('.', array_slice($keys, 0, -1));
            $lastKey = end($keys);

            if ($parentPath) {
                $parent = &$array;
                $parentKeys = explode('.', $parentPath);
                foreach ($parentKeys as $pk) {
                    $parent = &$parent[$pk];
                }
                unset($parent[$lastKey]);

                // Recursively clean up
                $this->cleanupEmptyArrays($array, $parentPath);
            } else {
                unset($array[$lastKey]);
            }
        }
    }
}