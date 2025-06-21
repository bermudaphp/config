<?php

namespace Bermuda\Config;

/**
 * Base configuration provider for dependency injection containers
 *
 * This class provides a structured way to define container configuration
 * including service definitions, factories, aliases, and application config.
 * Can be extended to create modular configuration providers.
 */
class ConfigProvider
{
    /** Configuration key for storing the main config object */
    public const CONFIG_KEY = 'config';

    /**
     * @var callable[] Child configuration providers
     */
    protected array $providers = [];

    /**
     * Constructor - automatically loads child providers
     *
     * Instantiates all providers returned by getProviders() method.
     * Child providers are merged recursively with the parent configuration.
     */
    final public function __construct()
    {
        foreach ($this->getProviders() as $provider) {
            $this->providers[] = $provider;
        }
    }

    /**
     * Get list of child configuration provider classes
     *
     * Override this method to define child providers that should be
     * automatically loaded and merged with this provider's configuration.
     *
     * @template T of ConfigProvider
     * @return callable[] Array of ConfigProvider
     */
    protected function getProviders(): array
    {
        return [];
    }

    /**
     * Get complete configuration array for container
     *
     * Returns the full configuration including dependencies and application config.
     * This method is typically called by the container configuration system.
     *
     * @return array Complete configuration array
     */
    public function __invoke(): array
    {
        $array = ['dependencies' => $this->getDependencies(), ... $this->getConfig()];
        if ($this->providers !== []) {
            foreach ($this->providers as $provider) {
                $array = array_replace_recursive($array, $provider());
            }
        }

        return $array;
    }

    /**
     * Get complete dependency injection configuration
     *
     * Merges all dependency configuration (factories, services, etc.)
     * from this provider and all child providers.
     *
     * @return array Complete dependency injection configuration
     */
    protected function getDependencies(): array
    {
        $dependencies = [
            'factories' => $this->getFactories(),
            'invokables' => $this->getInvokables(),
            'autowires' => $this->getAutowires(),
            'aliases' => $this->getAliases(),
            'delegators' => $this->getDelegators(),
            'services' => $this->getServices(),
        ];

        return $dependencies;
    }

    /**
     * Get application configuration
     *
     * Override this method to provide application-specific configuration
     * that will be accessible through the Config object.
     *
     * @return array Application configuration array
     */
    protected function getConfig(): array
    {
        return [];
    }

    /**
     * Get factory definitions
     *
     * An associative array that maps a service name to a factory class name, or any callable.
     * Factory classes must be instantiable without arguments, and callable once instantiated
     * (i.e., implement the __invoke() method).
     *
     * @return array Service name => factory class/callable mappings
     */
    protected function getFactories(): array
    {
        return [];
    }

    /**
     * Get invokable service definitions
     *
     * An associative array that maps a key to a constructor-less service; i.e., for services
     * that do not require arguments to the constructor. The key and service name usually are
     * the same; if they are not, the key is treated as an alias.
     *
     * @return array Service name => class name mappings for constructor-less services
     */
    protected function getInvokables(): array
    {
        return [];
    }

    /**
     * Get autowired service definitions
     *
     * An array of services with or without a constructor; the container offers an autowire
     * technique that will scan the code and see what parameters are needed in the constructors.
     * Any aliases needed should be created in the aliases configuration.
     *
     * @return array Service names or class names for autowired services
     */
    protected function getAutowires(): array
    {
        return [];
    }

    /**
     * Get service aliases
     *
     * An associative array that maps an alias to a service name (or another alias).
     * Useful for providing alternative names for services or implementing interfaces.
     *
     * @return array Alias => service name mappings
     */
    protected function getAliases(): array
    {
        return [];
    }

    /**
     * Get delegator factories
     *
     * An associative array that maps service names to lists of delegator factory keys.
     * Delegators allow you to decorate or modify services during creation.
     *
     * @return array Service name => delegator factory array mappings
     */
    protected function getDelegators(): array
    {
        return [];
    }

    /**
     * Get pre-instantiated services
     *
     * An associative array that maps a key to a specific service instance.
     * Use this for services that are already instantiated or for simple values.
     *
     * @return array Service name => service instance mappings
     */
    protected function getServices(): array
    {
        return [];
    }
}