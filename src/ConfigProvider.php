<?php

namespace Bermuda\Config;

class ConfigProvider
{
    public const containerConfigKey = 'config';
    public const dependencies = 'dependencies';
    public const factories = 'factories';
    public const invokables = 'invokables';
    public const autowires = 'autowires';
    public const aliases = 'aliases';
    public const delegators = 'delegators';
    public const services = 'services';
    public const bootstrap = 'bootstrap';

    /**
     * @var ConfigProvider[]
     */
    protected array $providers = [];

    final public function __construct()
    {
        foreach ($this->getProviders() as $provider) {
            $this->providers[] = new $provider;
        }
    }

    /**
     * @template T of ConfigProvider
     * @return class-string<T>[]
     */
    protected function getProviders(): array
    {
        return [];
    }

    public function __invoke(): array
    {
        $array = [self::dependencies => $this->getDependencies()];
        
        return ($cfg = $this->getMergedConfig()) !== [] ? array_replace($array, $cfg) : $array;
    }

    private function getMergedConfig(): array
    {
        if ($this->providers === []) return $this->getConfig();

        $config = $this->getConfig();
        foreach ($this->providers as $provider) {
            $config = array_replace_recursive($config, $provider->getMergedConfig());
        }

        return $config;
    }


    protected function getDependencies(): array
    {
        $dependencies = [
            self::factories => $this->getFactories(),
            self::invokables => $this->getInvokables(),
            self::autowires => $this->getAutowires(),
            self::aliases => $this->getAliases(),
            self::delegators => $this->getDelegators(),
            self::services => $this->getServices(),
        ];

        if ($this->providers !== []) {
            foreach ($this->providers as $provider) {
                $dependencies = array_replace_recursive($dependencies, $provider->getDependencies());
            }
        }

        return $dependencies;
    }

    protected function getConfig(): array
    {
        return [];
    }
    
    /**
     * An associative array that maps a service name to a factory class name, or any callable. 
     * Factory classes must be instantiable without arguments, and callable once instantiated (i.e., implement the __invoke() method).
     * @return array
     */
    protected function getFactories(): array
    {
        return [];
    }
    
    /**
     * An associative array that map a key to a constructor-less service; i.e., for services that do not require arguments to the constructor. 
     * The key and service name usually are the same; 
     * if they are not, the key is treated as an alias. It could also be an array of services.
     * @return array
     */
    protected function getInvokables(): array
    {
        return [];
    }
    
    /**
     * an array of service with or without a constructor; 
     * PHP-DI offers an autowire technique that will scan the code and see what are the parameters needed in the constructors. 
     * Any aliases needed should be created in the aliases configuration
     * @return array
     */
    protected function getAutowires(): array
    {
        return [];
    }
    
    /**
     * An associative array that maps an alias to a service name (or another alias).
     * @return array
     */
    protected function getAliases(): array
    {
        return [];
    }
    
    /**
     * An associative array that maps service names to lists of delegator factory keys
     * @return array
     */
    protected function getDelegators(): array
    {
        return [];
    }
    
    /**
     * An associative array that maps a key to a specific service instance or service name.
     * @return array
     */
    protected function getServices(): array
    {
        return [];
    }
}
