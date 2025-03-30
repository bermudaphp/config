<?php

namespace Bermuda\Config;

use Bermuda\ClassScanner\ClassFinder;
use Bermuda\ClassScanner\Filter\AttributeFilter;

class AttributeProvider
{
    public function __construct(
        private readonly string $directory,
    ) {
    }

    /**
     * @throws \ReflectionException
     */
    public function __invoke(): array
    {
        $data = [];
        foreach (new ClassFinder(filters: [new AttributeFilter(AsConfig::class)])->find($this->directory) as $cls) {
            if (!$cls->hasMethod('__invoke')) {
                throw new \RuntimeException('Invalid provider: ' . $cls->getName());
            }

            $provider = $cls->newInstanceWithoutConstructor();
            $data = array_merge_recursive($data, $provider->__invoke());
        }

        return $data;
    }
}
