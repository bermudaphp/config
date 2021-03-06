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

    public static bool $devMode = true;
    public static ?string $cacheFile = null;

    /**
     * @param callable ...$providers
     * @return array
     */
    public static function merge(callable ... $providers): array
    {
        return (new ConfigAggregator(array_merge($providers, [static fn(): array => ['debug' => self::$devMode, ConfigAggregator::ENABLE_CACHE => !self::$devMode && self::$cacheFile != null]])))->getMergedConfig();
    }
}
