<?php

namespace Bermuda\Config;

use Bermuda\Config\Key;
use Bermuda\Stdlib\NumberConverter;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Get service from container with default fallback
 *
 * Safely retrieves a service from the container, returning the default value
 * if the service is not found instead of throwing an exception.
 *
 * @param ContainerInterface $container The PSR-11 container
 * @param string $id Service identifier
 * @param mixed $default Default value to return if service not found
 * @return mixed The service instance or default value
 * @throws ContainerExceptionInterface Error while retrieving the entry.
 */
function cget(ContainerInterface $container, string $id, mixed $default = null): mixed
{
    if ($container->has($id)) {
        return $container->get($id);
    }
    return $default;
}

/**
 * Get configuration object from container
 *
 * Retrieves the main configuration object from the container.
 * This is a shorthand for getting the config service.
 *
 * @param ContainerInterface $container The PSR-11 container
 * @return Config The configuration object
 * @throws ContainerExceptionInterface Error while retrieving the entry.
 * @throws NotFoundExceptionInterface No entry was found for the config identifier.
 */
function conf(ContainerInterface $container): Config
{
    return $container->get(ConfigProvider::CONFIG_KEY);
}

/**
 * Get an environment variable with automatic type conversion and default fallback
 *
 * This function retrieves environment variables and automatically converts string values
 * to appropriate PHP types (numbers, booleans) when possible. It reads directly from
 * the $_ENV superglobal, which means values can change during script execution.
 *
 * For immutable environment access, use the Environment object from Config instead.
 *
 * Type conversions:
 * - Numeric strings are converted to int/float
 * - "true", "yes", "y", "on", "enabled" → true (case-insensitive)
 * - "false", "no", "n", "off", "disabled" → false (case-insensitive)
 * - Other values are returned as-is
 *
 * @param string $key The environment variable name
 * @param mixed $default Default value to return if the variable doesn't exist
 * @return mixed The environment variable value with type conversion applied, or default
 */
function env(string $key, mixed $default = null): mixed
{
    if (isset($_ENV[$key]) || array_key_exists($key, $_ENV)) {
        $value = $_ENV[$key];

        if (is_string($value)) {
            // Auto-convert numeric values
            if (NumberConverter::isNumeric($value)) {
                return NumberConverter::convertValue($value);
            }

            // Convert boolean-like strings
            $lowerValue = strtolower(trim($value));
            if (in_array($lowerValue, ['true', 'yes', 'y', 'on', 'enabled'], true)) {
                return true;
            }
            if (in_array($lowerValue, ['false', 'no', 'n', 'off', 'disabled'], true)) {
                return false;
            }
        }

        return $value;
    }

    return $default;
}

/**
 * Get an environment variable as string
 *
 * @param string $key Environment variable name
 * @param string $default Default value
 * @return string Environment variable value as string
 */
function env_string(string $key, string $default = ''): string
{
    $value = env($key, $default);
    return is_scalar($value) ? (string) $value : $default;
}

/**
 * Get an environment variable as integer
 *
 * @param string $key Environment variable name
 * @param int $default Default value
 * @return int Environment variable value as integer
 */
function env_int(string $key, int $default = 0): int
{
    $value = env($key, $default);

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
 * Get an environment variable as float
 *
 * @param string $key Environment variable name
 * @param float $default Default value
 * @return float Environment variable value as float
 */
function env_float(string $key, float $default = 0.0): float
{
    $value = env($key, $default);

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
 * Get an environment variable as boolean
 *
 * @param string $key Environment variable name
 * @param bool $default Default value
 * @return bool Environment variable value as boolean
 */
function env_bool(string $key, bool $default = false): bool
{
    $value = env($key, $default);

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
 * Get an environment variable as array (comma-separated)
 *
 * @param string $key Environment variable name
 * @param array $default Default value
 * @return array Environment variable value as array
 */
function env_array(string $key, array $default = []): array
{
    $value = env($key);

    if ($value === null) {
        return $default;
    }

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
 * Create a literal key wrapper for keys containing dots
 *
 * Use this when you need to access a configuration key that literally contains dots
 * rather than using dot notation for nested access.
 *
 * @param string $key The literal key name containing dots
 * @return Key Key wrapper for literal access
 */
function key(string $key): Key
{
    return new Key($key);
}