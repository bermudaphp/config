<?php

namespace Bermuda\Config; 

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


