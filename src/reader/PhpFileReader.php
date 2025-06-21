<?php

namespace Bermuda\Config\Reader;

/**
 * PHP file reader for configuration files
 *
 * Reads and includes PHP configuration files (.php extension) that return arrays.
 * Returns null if the file is not a PHP file or doesn't return an array.
 */
final class PhpFileReader implements FileReaderInterface
{
    /**
     * Read and include a PHP configuration file
     *
     * The PHP file should return an array when included.
     *
     * @param string $file Path to the PHP file
     * @return array|null Included array data or null if not supported/failed
     */
    public function readFile(string $file): ?array
    {
        if (!str_ends_with($file, '.php')) return null;

        $data = include $file;
        if (empty($data) || !is_array($data)) return null;

        return $data;
    }
}
