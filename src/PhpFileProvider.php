<?php

namespace Bermuda\Config;

use Bermuda\Flysystem\Location;
use Laminas\ConfigAggregator\GlobTrait;

final class PhpFileProvider
{
    use GlobTrait;
    private $normalizer;

    public function __construct(
        private readonly string $pattern,
        private readonly bool $filenameAsKey = true,
        ?callable $normalizer = null
    ) {
        $this->normalizer = $normalizer;
    }

    public function __invoke(): array
    {
        $cfg = [];
        foreach ($this->glob($this->pattern) as $file) {
            if (str_ends_with($basename = basename($file), '.php')) {
                if (empty($data = include $file) || !is_array($data)) continue ;
                if ($this->filenameAsKey) $data = [$this->normalize($basename) => $data];
                
                $cfg = array_merge_recursive($cfg, $data);
            }
        }

        return $cfg;
    }

    private function normalize(string $basename): string
    {
        if ($this->normalizer) return ($this->normalizer)($basename);
        else return str_replace(['.', 'php', 'local', 'development'], '', $basename);
    }
}
