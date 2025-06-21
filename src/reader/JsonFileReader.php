<?php

namespace Bermuda\Config\Reader;

/**
 * JSON file reader for configuration files
 *
 * Reads and parses JSON configuration files (.json extension).
 * Returns null if the file is not a JSON file or parsing fails.
 */
final class JsonFileReader implements FileReaderInterface
{
    /**
     * Read and parse a JSON configuration file
     *
     * @param string $file Path to the JSON file
     * @return array|null Parsed JSON data as array or null if not supported/failed
     */
    public function readFile(string $file): ?array
    {
        if (!str_ends_with($file, '.json')) return null;

        $data = file_get_contents($file);
        if ($data === false) return null;

        $decoded = json_decode($data, true);
        return is_array($decoded) ? $decoded : null;
    }
}
