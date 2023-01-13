<?php

namespace Bermuda\Config;

use Laminas\ConfigAggregator\ConfigAggregator;

final class Config
{
    public static bool $devMode = true;
    public static ?string $cacheFile = null;
    
    public const app_timezone = 'app.timezone';
    public const app_debug_mode_enable = 'app.debug';
    
    /**
     * @param callable ...$providers
     * @return array
     */
    public static function merge(callable ...$providers): array
    {
        return (new ConfigAggregator(
            array_merge($providers, 
                [
                    static fn(): array => [
                        self::app_debug_mode_enable => self::$devMode,
                        ConfigAggregator::ENABLE_CACHE => !self::$devMode && self::$cacheFile != null
                    ]
                ]
            ), Config::$cacheFile
        ))->getMergedConfig();
    }
}
