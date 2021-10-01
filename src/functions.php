<?php

namespace Bermuda\Config;

/**
 * @param string $entry
 * @param null $default
 * @return AppInterface|mixed|string|null
 */
function cget(ContainerInterface $container, string $id, $default = null, bool $invokable = false): mixed
{
    if ($container->has($id)) {
    
    }
}
