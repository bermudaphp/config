<?php

namespace Bermuda\Config;

use Bermuda\VarExport\VarExporter;
use Laminas\ConfigAggregator\ConfigAggregator;
use Webimpress\SafeWriter\FileWriter;
use function Bermuda\Stdlib\to_array;

final class Config
{
    public static bool $devMode = true;
    public static ?string $cacheFile = null;
    
    public const app_config = \Elie\PHPDI\Config\Config::CONFIG;
    public const app_timezone = 'app.timezone';
    public const app_debug_mode_enable = 'app.debug';
    
    /**
     * @param callable ...$providers
     * @return array
     */
    public static function merge(callable ...$providers): array
    {
        $providers = array_merge($providers, [
            static fn(): array => [
                self::app_debug_mode_enable => self::$devMode
            ]
        ]);

        $cfg = [];
        foreach ($providers as $provider) {
            $cfg = array_merge_recursive($cfg, self::wrapCallable(to_array($provider())));
        }

        return $cfg;
    }
    
    public static function fromCache(string $filename): array
    {
        if (file_exists($filename)) {
            return include $filename;
        }

        throw new \RuntimeException('No such file '. $filename);
    }

    /**
     * @param string $file
     * @param array $config
     * @return void
     * @throws \Bermuda\VarExport\ExportException
     * @throws \Webimpress\SafeWriter\Exception\ExceptionInterface
     */
    public static function writeCachedConfig(string $file, array $config): void
    {
        FileWriter::writeFile($file, '<?php'.PHP_EOL.'  return'.VarExporter::export($config));
    }
    
    private static function wrapCallable(array $data): array
    {
        $cfg = [];
        foreach ($data as $key => $value) {
            if ($key == ConfigProvider::dependencies) $cfg[$key] = $value;
            else if (is_array($value)) $cfg[$key] = self::wrapCallable($value);
            else if (is_callable($value)) $cfg[$key] = static fn() => $value;
            else $cfg[$key] = $value ;
        }

        return $cfg;
    }
}
