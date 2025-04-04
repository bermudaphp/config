<?php

namespace Bermuda\Config;

use Bermuda\Stdlib\Arrayable;
use Bermuda\VarExport\VarExporter;
use Laminas\ConfigAggregator\ConfigAggregator;
use Psr\Container\ContainerInterface;
use Webimpress\SafeWriter\FileWriter;

final class Config implements \Countable, \IteratorAggregate, \ArrayAccess, Arrayable
{
    public static bool $devMode = true;
    public static ?string $cacheFile = null;
    
    public const app_config = \Elie\PHPDI\Config\Config::CONFIG;
    public const app_timezone = 'app.timezone';
    public const app_debug_mode_enable = 'app.debug';

    private array $data = [];

    public function __construct(iterable $data)
    {
        foreach ($data as $key => $value) {
            $this->data[$key] = is_iterable($value) ? new Config($value) : $value;
        }
    }

    /**
     * @param ContainerInterface $container
     * @return static
     */
    public static function createConfig(ContainerInterface $container): self
    {
        return new self($container->get(self::app_config));
    }
    
    public function getIterator(): \Generator
    {
        foreach ($this->data as $k => $v) yield $k => $v;
    }

    /**
     * @param $name
     * @return Config|mixed
     */
    public function __get(int|string|float $name): mixed
    {
        return $this->offsetGet($name);
    }

    /**
     * @throws RuntimeException
     */
    public function __set(int|string|float $name, mixed $value)
    {
        $this->offsetSet($name, $value);
    }

    /**
     * @return Config|mixed
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    /**
     * @return self|null|mixed
     */
    public function get(int|string|float $offset, mixed $default = null, bool $invoke = true, bool $toArray = true): mixed
    {
        $data = $this->offsetExists($offset) ? $this->data[$offset]
            : ($invoke && is_callable($default) ? $default() : $default);

        if ($toArray && $data instanceof Arrayable) return $data->toArray();

        return $data;
    }

    /**
     * @throws RuntimeException
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw $this->notImmutable();
    }

    /**
     * @inerhitDoc
     */
    public function toArray(): array
    {
        $array = [];

        foreach ($this->data as $key => $value) {
            $array[$key] = $value instanceof Config
                ? $value->toArray() : $value;
        }

        return $array;
    }

    public function __isset(string|int|float $offset): bool
    {
        return $this->offsetExists($offset);
    }

    /**
     * @inerhitDoc
     */
    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->data);
    }

    /**
     * @throws RuntimeException
     */
    public function __unset(string|int|float $name): void
    {
        $this->offsetUnset($name);
    }

    /**
     * @throws RuntimeException
     */
    public function offsetUnset(mixed $offset): void
    {
        throw $this->notImmutable();
    }

    /**
     * @inerhitDoc
     */
    public function count(bool $recursive = false): int
    {
        return count($this->data);
    }
    
    private function notImmutable(): RuntimeException
    {
        return new RuntimeException('Config is not immutable');
    }
    
    /**
     * @param callable ...$providers
     * @return array
     */
    public static function merge(callable ...$providers): array
    {
        $cfg = [self::app_debug_mode_enable => self::$devMode];
        foreach ($providers as $provider) {
            foreach ($provider() as $key => $value) {
                if ($key === ConfigProvider::dependencies) {
                    foreach ($value as $dependencyKey => $dependencyValue) {
                        foreach ($dependencyValue as $k => $v) {
                            $cfg[ConfigProvider::dependencies][$dependencyKey][$k] = $v;
                        }
                    }
                } else if ($key == ConfigProvider::bootstrap) {
                    if (!array_key_exists($key, $cfg)) {
                        $cfg[$key] = [];
                    }

                    if (is_array($value)) $cfg[$key] = array_merge($cfg[$key], $value);
                    else $cfg[$key][] = $value;
                } else {
                    if (array_key_exists($key, $cfg) && is_array($cfg[$key])) {
                        if (is_array($value)) $cfg[$key] = array_merge($cfg[$key], $value);
                        else $cfg[$key] = $value;
                    } else $cfg[$key] = $value;
                }
            }

        }

        return $cfg;
    }
    
    public static function fromCache(string $filename): array
    {
        if (file_exists($filename)) return include $filename;
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
}
