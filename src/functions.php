<?php

namespace Bermuda\Config {
    use Psr\Container\ContainerInterface;

    /**
     * @param ContainerInterface $container
     * @param string $id
     * @param mixed $default
     * @param bool $invokable
     * @throws NotFoundExceptionInterface  No entry was found for **this** identifier.
     * @throws ContainerExceptionInterface Error while retrieving the entry.
     * @return mixed
     */
    function cget(ContainerInterface $container, string $id, $default = null, bool $invokable = true): mixed
    {
        if ($container->has($id)) {
            return $container->get($id);
        }

        return $invokable && is_callable($default) ? $default($container) : $default ;
    }
}

namespace Bermuda\Stdlib {
    if (!function_exists('to_array')) {
        function to_array(iterable|object $arrayable): array
        {
            if ($arrayable instanceof Arrayable) return $arrayable->toArray();
            if ($arrayable instanceof \IteratorAggregate) return \iterator_to_array($arrayable->getIterator());
            if ($arrayable instanceof \Iterator) return \iterator_to_array($arrayable);
            if (is_array($arrayable)) return $arrayable;

            return \get_object_vars($arrayable);
        }
    }
}
