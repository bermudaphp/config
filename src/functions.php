<?php

namespace Bermuda\Config;

use Psr\Container\ContainerInterface;

/**
 * @param ContainerInterface $container
 * @param string $id
 * @param mixed $default
 * @param bool $invokable
 * @throws ContainerExceptionInterface Error while retrieving the entry.
 * @return mixed
 */
function cget(ContainerInterface $container, string $id, $default = null, bool $invokable = true): mixed
{
    try {
        return $container->get($id);
    } catch (NotFoundExceptionInterface) {
        return $invokable && is_callable($default) ? $default($container) : $default ;
    }
}
