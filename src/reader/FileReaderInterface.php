<?php

namespace Bermuda\Config\Reader;

/**
 * Interface for file readers that can parse configuration files
 *
 * File readers are responsible for determining if they can handle a specific
 * file format and parsing it into a PHP array. Implementations should return
 * null if they cannot handle the given file.
 */
interface FileReaderInterface
{
    /**
     * Read and parse a configuration file
     *
     * Implementations should:
     * - Check if the file format is supported (usually by extension)
     * - Read and parse the file content
     * - Return null if the file format is not supported or parsing fails
     * - Return array if parsing is successful
     *
     * @param string $file Path to the configuration file
     * @return array|null Parsed configuration array or null if not supported/failed
     */
    public function readFile(string $file): ?array;
}
