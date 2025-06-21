<?php

namespace Bermuda\Config\Env;

/**
 * Exception thrown when environment variable loading fails
 *
 * This exception is thrown by EnvLoader implementations when:
 * - .env files cannot be found in specified paths
 * - .env files cannot be read or parsed
 * - Required environment variables are missing after loading
 * - Environment variable validation fails
 * - File format errors are encountered during parsing
 *
 * The exception message should provide clear information about what
 * went wrong to help with debugging environment configuration issues.
 */
class EnvLoaderException extends \RuntimeException
{
    /**
     * Create exception for missing .env files
     *
     * @param array $paths Searched paths
     * @param array $files Searched filenames
     * @return self
     */
    public static function filesNotFound(array $paths, array $files): self
    {
        return new self(
            'No .env files found in paths: ' . implode(', ', $paths) .
            ' (looking for: ' . implode(', ', $files) . ')'
        );
    }

    /**
     * Create exception for missing required variables
     *
     * @param array $missing Missing variable names
     * @return self
     */
    public static function requiredVariablesMissing(array $missing): self
    {
        return new self(
            'Required environment variables are missing: ' . implode(', ', $missing)
        );
    }

    /**
     * Create exception for file parsing errors
     *
     * @param string $file File path
     * @param int $line Line number
     * @param string $content Line content
     * @return self
     */
    public static function parseError(string $file, int $line, string $content): self
    {
        return new self(
            "Invalid .env format on line $line in file '$file': $content"
        );
    }

    /**
     * Create exception for file access errors
     *
     * @param string $file File path
     * @param string $reason Reason for access failure
     * @return self
     */
    public static function fileNotReadable(string $file, string $reason = 'file is not readable'): self
    {
        return new self("Cannot read environment file '$file': $reason");
    }
}