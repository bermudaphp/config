<?php

namespace Bermuda\Config;


use Laminas\ConfigAggregator\ArrayProvider;
use Laminas\ConfigAggregator\ConfigAggregator;


/**
 * Class Config
 * @package Bermuda\Config
 */
final class Config
{
    public function __construct()
    {
        throw new \RuntimeException(__CLASS__ . ' is not instantiable!');
    }

    public static bool $useCache = false;
    public static ?string $cacheFile = null;

    /**
     * @param callable ...$providers
     * @return array
     */
    public static function merge(callable ... $providers): array
    {
        $providers[] = static function()
        {
            return [ConfigAggregator::ENABLE_CACHE => self::$useCache];
        };
        
        return (new ConfigAggregator($providers, self::$cacheFile))->getMergedConfig();
    }
}
