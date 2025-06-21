<?php

namespace Bermuda\Config\Reader;

use Symfony\Component\Yaml\Yaml;

/**
 * YAML file reader for configuration files
 *
 * Reads and parses YAML configuration files (.yaml extension) using Symfony YAML component.
 * Returns null if the file is not a YAML file or parsing fails.
 *
 * @requires symfony/yaml
 */
final class YamlFileReader implements FileReaderInterface
{
    /**
     * Read and parse a YAML configuration file
     *
     * @param string $file Path to the YAML file
     * @return array|null Parsed YAML data as array or null if not supported/failed
     */
    public function readFile(string $file): ?array
    {
        if (!str_ends_with($file, '.yaml') && !str_ends_with($file, '.yml')) return null;

        $content = file_get_contents($file);
        if ($content === false) return null;

        try {
            $data = Yaml::parse($content);
            return is_array($data) ? $data : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}