<?php

namespace Bermuda\Config;

use Bermuda\Config\Env\EnvLoader;
use Bermuda\Config\Env\EnvLoaderInterface;
use Bermuda\VarExport\VarExporter;
use Webimpress\SafeWriter\FileWriter;
use Psr\Container\ContainerInterface;

/**
 * Configuration loader with caching support and provider aggregation
 *
 * Responsible for loading configuration from various sources,
 * caching results and aggregating data from multiple providers.
 * Supports automatic environment variable loading and file-based caching
 * for improved performance in production environments.
 *
 * When cache file is provided in constructor, environment loading is disabled
 * and configuration is loaded from cache, ignoring all providers.
 */
final class ConfigLoader
{
    /** @var string|null Cache file path */
    public ?string $cacheFile = null;

    /** @var EnvLoaderInterface|null Environment variable loader instance */
    private ?EnvLoaderInterface $envLoader;

    /**
     * Configuration loader constructor
     *
     * Creates a new ConfigLoader instance with optional environment loading
     * and caching support. When cacheFile is provided, environment loading
     * is disabled (envPaths is set to null) and the loader operates in cache mode.
     * If envPaths is provided and no cacheFile is set, automatically sets up
     * environment variable loading from .env files.
     *
     * @param string|string[]|null $envPaths Path(s) to directory containing .env file(s) or null to skip loading
     * @param string|null $cacheFile Cache file path for storing processed configuration (optional)
     */
    public function __construct(string|array|null $envPaths = null, ?string $cacheFile = null)
    {
        if ($cacheFile !== null) {
            $this->cacheFile = $cacheFile;
            $envPaths = null;
        }

        if ($envPaths !== null) {
            $this->envLoader = new EnvLoader($envPaths);
        }
    }

    /**
     * Create ConfigLoader instance with environment loader
     *
     * Factory method for creating a ConfigLoader with an environment loader
     * pre-configured. This is a convenience method for the common use case
     * where environment loading is the primary requirement.
     *
     * @param EnvLoaderInterface $envLoader Environment variable loader instance
     * @param string|null $cacheFile Optional path to cache file for performance optimization
     * @return self Configured ConfigLoader instance with environment loader attached
     */
    public static function createWithLoader(EnvLoaderInterface $envLoader, ?string $cacheFile = null): self
    {
        return new self(cacheFile: $cacheFile)->setEnvLoader($envLoader);
    }

    /**
     * Set or replace the environment variable loader
     *
     * Allows setting an environment loader after ConfigLoader instantiation.
     * This method supports fluent interface pattern by returning the current
     * instance, enabling method chaining. Useful for dynamic configuration
     * or when the environment loader needs to be changed at runtime.
     *
     * @param EnvLoaderInterface $loader Environment variable loader to set
     * @return ConfigLoader Returns this instance for method chaining
     */
    public function setEnvLoader(EnvLoaderInterface $loader): ConfigLoader
    {
        $this->envLoader = $loader;
        return $this;
    }

    /**
     * Load and create configuration object
     *
     * Processes configuration providers in the order they are provided,
     * merging their data. If cache file is configured, attempts to load from
     * cache first and completely ignores all providers. When cache file is set
     * but cache loading fails, throws RuntimeException. Environment variables
     * are loaded only if envLoader is configured and no cache file is used.
     *
     * @param callable ...$providers Configuration providers that return arrays (ignored if cache file exists)
     * @return Config Created configuration object with aggregated data
     * @throws \RuntimeException On loading errors or when cache file is set but cache loading fails
     * @throws \InvalidArgumentException If provider doesn't return array or iterable
     */
    public function load(callable ...$providers): Config
    {
        $environment = null;
        $envData = null;

        // Load environment variables if loader is configured
        if ($this->envLoader) {
            $envData = $this->envLoader->load();
            $environment = new Environment($envData);
        }

        // Try to load from cache if cache file is configured
        if ($this->cacheFile !== null) {
            $cachedData = $this->loadFromCache();
            if ($cachedData !== null) {
                // Cache contains both config and environment data
                $configData = $cachedData['config'] ?? [];
                $cachedEnvData = $cachedData['environment'] ?? null;

                // Use cached environment if available and no new env loader provided
                if ($cachedEnvData !== null && $environment === null) {
                    $environment = new Environment($cachedEnvData);
                }

                return new Config($configData, $environment);
            }

            throw new \RuntimeException(
                "Failed to load configuration from cache file: $this->cacheFile. " .
                "Cache file may be missing, corrupted, or unreadable."
            );
        }

        // Aggregate data from all providers when no cache or cache disabled
        $configData = $this->aggregateProviders($providers);

        return new Config($configData, $environment);
    }

    /**
     * Load and create configuration object with container support
     *
     * Same as load() but attaches a dependency injection container to the
     * resulting configuration object. The container becomes accessible
     * within callable configuration values through $config->container property,
     * enabling service resolution and dependency injection patterns.
     * Uses setContainer method to attach the container after loading.
     *
     * @param ContainerInterface $container Dependency injection container for service resolution
     * @param callable ...$providers Configuration providers that return arrays (ignored if cache file exists)
     * @return Config Created configuration object with container attached
     * @throws \RuntimeException On loading errors or when cache file is set but cache loading fails
     * @throws \InvalidArgumentException If provider doesn't return array or iterable
     */
    public function loadWithContainer(ContainerInterface $container, callable ...$providers): Config
    {
        ($config = $this->load(...$providers))->setContainer($container);
        return $config;
    }

    /**
     * Aggregate configuration data from all providers
     *
     * Calls each provider in sequence and merges their returned data using
     * array_merge_recursive. Later providers can override values from earlier
     * providers. Supports both array and iterable return values from providers.
     * Wraps provider execution in try-catch to provide detailed error information.
     *
     * @param callable[] $providers Array of configuration providers
     * @return array Merged configuration data from all providers
     * @throws \RuntimeException If provider execution fails
     * @throws \InvalidArgumentException If provider doesn't return array or iterable
     */
    private function aggregateProviders(array $providers): array
    {
        if (empty($providers)) {
            return [];
        }

        $merged = [];

        foreach ($providers as $provider) {
            try {
                $data = $provider();

                if (!is_array($data)) {
                    if (is_iterable($data)) {
                        $data = iterator_to_array($data);
                    } else {
                        throw new \InvalidArgumentException(
                            'Configuration provider must return array or iterable object'
                        );
                    }
                }

                $merged = array_merge_recursive($merged, $data);

            } catch (\Throwable $e) {
                throw new \RuntimeException(
                    'Error executing configuration provider: ' . $e->getMessage(),
                    0,
                    $e
                );
            }
        }

        return $merged;
    }

    /**
     * Load configuration data from cache file
     *
     * Attempts to include and validate the cache file. Returns null if cache
     * file doesn't exist, is unreadable, or doesn't contain valid array data.
     * Uses include to load the cache file, expecting it to return an array.
     * This method assumes cacheFile property is already set and not null.
     *
     * @return array|null Configuration data array or null if cache miss or invalid
     */
    private function loadFromCache(): ?array
    {
        try {
            $data = include $this->cacheFile;
            return is_array($data) ? $data : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Export configuration data to cache file
     *
     * Writes the provided data to the specified file as executable PHP code that returns
     * the data array. Creates cache directory if it doesn't exist.
     *
     * @param string $filename Path to the file where configuration should be exported
     * @throws \RuntimeException On file write error or directory creation failure
     */
    public function export(string $filename): void
    {
        try {
            $directory = dirname($filename);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $data = VarExporter::exportPretty($data);
            $content = "<?php\n\nreturn " . $data . ";\n";

            FileWriter::writeFile($filename, $content);

        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "Failed to export configuration to file: $filename. " .
                "Error: " . $e->getMessage(),
                0,
                $e
            );
        }
    }
}