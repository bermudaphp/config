<?php

namespace Bermuda\Config\Env;

/**
 * Interface for environment variable loaders
 *
 * Defines the contract for classes that load environment variables from
 * .env files or other sources. Implementations should handle finding,
 * parsing, validating, and loading environment variables into the
 * PHP environment ($_ENV, $_SERVER superglobals).
 */
interface EnvLoaderInterface
{
    /**
     * Load environment variables from configured sources
     *
     * Finds and parses .env files in specified paths (typically configured
     * through constructor), validates them according to loader configuration,
     * populates the $_ENV and $_SERVER superglobals, and returns the final
     * array with all found values.
     *
     * The returned array should contain all environment variables that were
     * successfully loaded and validated. This array is typically used to
     * create an Environment instance.
     *
     * @return array Associative array of environment variable name => value pairs
     * @throws EnvLoaderException If .env files cannot be found, parsed, or validated
     */
    public function load(): array;
}
