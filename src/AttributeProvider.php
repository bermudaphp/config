<?php

namespace Bermuda\Config;

use Bermuda\ClassFinder\ClassFinder;
use Bermuda\ClassFinder\Filter\AttributeFilter;

class AttributeProvider
{
    public function __construct(
        private readonly string|array $dirs,
        private readonly string|array $exclude = []
    ) {
    }

    /**
     * @throws \ReflectionException
     */
    public function __invoke(): array
    {
        $data = [];
        foreach (new ClassFinder(filters: [new AttributeFilter(AsConfig::class)])->find($this->dirs, $this->exclude) as $cls) {
            if (!$cls->isInvokable()) {
                throw new \RuntimeException('Invalid provider: ' . $cls->getName());
            }

            $provider = $cls->newInstanceWithoutConstructor();
            $data = array_merge_recursive($data, $provider->__invoke());
        }

        return $data;
    }
}
