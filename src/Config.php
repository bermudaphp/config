<?php

namespace Bermuda\Config;

use Bermuda\Stdlib\Arrayable;
use Bermuda\VarExport\VarExporter;
use Psr\Container\ContainerInterface;
use Laminas\ConfigAggregator\ConfigAggregator;
use Webimpress\SafeWriter\FileWriter;
use function Bermuda\Stdlib\to_array;

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
     * @return mixed|self
     */
    public function __get($name)
    {
        return $this->offsetGet($name);
    }

    /**
     * @throws RuntimeException
     */
    public function __set($name, $value)
    {
        $this->offsetSet($name, $value);
    }

    /**
     * @inerhitDoc
     */
    public function offsetGet($offset): mixed
    {
        return $this->get($offset);
    }

    /**
     * @param $offset
     * @param null $default
     * @param bool $invoke
     * @return self|null|mixed
     */
    public function get($offset, $default = null, bool $invoke = true)
    {
        return $this->offsetExists($offset) ? $this->data[$offset]
            : ($invoke && is_callable($default) ? $default() : $default);
    }

    /**
     * @throws RuntimeException
     */
    public function offsetSet($offset, $value): void
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

    /**
     * @param $name
     * @return bool
     */
    public function __isset($name): bool
    {
        return $this->offsetExists($name);
    }

    /**
     * @inerhitDoc
     */
    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->data);
    }

    /**
     * @throws RuntimeException
     */
    public function __unset($name): void
    {
        $this->offsetUnset($name);
    }

    /**
     * @throws RuntimeException
     */
    public function offsetUnset($offset): void
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
