<?php

namespace Bermuda\Config;

use Bermuda\ClassScanner\ClassFinder;

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
        foreach (new ClassFinder()->find($this->directory) as $reflectionClass) {
            if ($reflectionClass->getAttributes(AsConfig::class)[0] ?? null) {
                if (!$reflectionClass->hasMethod('__invoke')) {
                    throw new \RuntimeException('Invalid provider: ' . $reflectionClass->getName());
                }

                $provider = $reflectionClass->newInstanceWithoutConstructor();
                $data = array_merge_recursive($data, $provider->__invoke());
            }
        }

        return $data;
    }
}
