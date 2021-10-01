<?php

namespace Bermuda\Config;

use Laminas\ConfigAggregator\ConfigAggregator;

final class Config
{
    public static bool $devMode = true;
    public static ?string $cacheFile = null;
    
    /**
     * @param callable ...$providers
     * @return array
     */
    public static function merge(callable ...$providers): array
    {
        return (new ConfigAggregator(array_merge($providers, [static fn(): array => ['debug' => self::$devMode, ConfigAggregator::ENABLE_CACHE => !self::$devMode && self::$cacheFile != null]])))->getMergedConfig();
    }
}
