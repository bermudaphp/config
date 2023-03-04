<?php

namespace Bermuda\Config;

use \Laminas\ConfigAggregator\PhpFileProvider as LaminasFileProvider;

class PhpFileProvider
{
    private LaminasFileProvider $provider;

    public function __construct(string $pattern)
    {
        $this->provider = new LaminasFileProvider($pattern);
    }

    public function __invoke(): array
    {
        $cfg = [];
        foreach (($this->provider)() as $item) {
            if (empty($item) || !is_array($item)) continue ;
            $cfg = array_merge_recursive($cfg, $item);
        }

        return $cfg;
    }
}
