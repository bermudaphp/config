<?php

namespace Bermuda\Config\Env;

/**
 * Environment variable loader from .env files
 *
 * This class loads environment variables from .env files found in specified paths.
 * Supports multiple search paths, various .env file names, automatic type conversion,
 * validation of required variables, and proper handling of quoted values with escape sequences.
 *
 * Features:
 * - Multiple .env file support (.env, .env.local, .env.example, etc.)
 * - Quoted value parsing with escape sequence support
 * - Variable name validation according to POSIX standards
 * - Required variable validation after loading
 * - Populates both $_ENV and $_SERVER superglobals
 * - Override protection for existing environment variables
 *
 * File format support:
 * - Simple assignments: KEY=value
 * - Quoted values: KEY="quoted value" or KEY='single quoted'
 * - Multiline values in double quotes with escape sequences
 * - Comments starting with # (ignored)
 * - Empty lines (ignored)
 */
final class EnvLoader implements EnvLoaderInterface
{
    /** Default .env file names to search for in order of priority */
    private const DEFAULT_ENV_FILES = ['.env', '.env.local', '.env.example'];

    /** @var string[] Paths to search for .env files */
    private array $paths;

    /** @var string[] .env file names to look for */
    private array $envFiles;

    /** @var array Variables that must be present after loading */
    private array $requiredVars;

    /** @var bool Whether to override existing environment variables */
    private bool $override;

    /** @var bool Whether to populate $_SERVER in addition to $_ENV */
    private bool $populateServer;

    /**
     * Constructor for EnvLoader
     *
     * @param string|string[] $paths Path(s) to search for .env files
     * @param string|string[]|null $envFiles .env file names to look for (defaults to .env, .env.local, .env.example)
     * @param string[]|null $requiredVars Environment variables that must be present after loading
     * @param bool $override Whether to override existing environment variables (default: false)
     * @param bool $populateServer Whether to populate $_SERVER in addition to $_ENV (default: true)
     */
    public function __construct(
        string|array $paths,
        string|array|null $envFiles = null,
        ?array $requiredVars = null,
        bool $override = false,
        bool $populateServer = true
    ) {
        $this->paths = is_array($paths) ? $paths : [$paths];
        $this->envFiles = $envFiles ? (is_array($envFiles) ? $envFiles : [$envFiles]) : self::DEFAULT_ENV_FILES;
        $this->requiredVars = $requiredVars ?? [];
        $this->override = $override;
        $this->populateServer = $populateServer;
    }

    /**
     * Load environment variables from .env files
     *
     * Searches for .env files in all configured paths and attempts to load them.
     * Files are processed in the order they are found, with later files potentially
     * overriding earlier ones (if override is enabled). After loading, validates
     * that all required variables are present.
     *
     * @return array Associative array of loaded environment variables
     * @throws EnvLoaderException If required files or variables are missing, or parsing fails
     */
    public function load(): array
    {
        $loadedVars = [];
        $foundFiles = [];

        // Search for .env files in all specified paths
        foreach ($this->paths as $path) {
            if (!is_dir($path)) {
                continue;
            }

            foreach ($this->envFiles as $envFile) {
                $filePath = rtrim($path, '/\\') . DIRECTORY_SEPARATOR . $envFile;

                if (file_exists($filePath) && is_readable($filePath)) {
                    $foundFiles[] = $filePath;
                    $fileVars = $this->parseEnvFile($filePath);
                    $loadedVars = array_merge($loadedVars, $fileVars);
                }
            }
        }

        // Check if any files were found
        if (empty($foundFiles)) {
            throw EnvLoaderException::filesNotFound($this->paths, $this->envFiles);
        }

        // Populate environment superglobals
        $this->populateEnvironment($loadedVars);

        // Check for required variables
        if (!empty($this->requiredVars)) {
            $missing = array_diff($this->requiredVars, array_keys($loadedVars));
            if (!empty($missing)) {
                throw EnvLoaderException::requiredVariablesMissing($missing);
            }
        }

        return $loadedVars;
    }

    /**
     * Parse a single .env file and extract environment variables
     *
     * Reads the file line by line, ignoring comments and empty lines,
     * and parses variable assignments with proper quote handling.
     *
     * @param string $filePath Path to the .env file to parse
     * @return array Parsed environment variables from the file
     * @throws EnvLoaderException If file cannot be read or contains invalid syntax
     */
    private function parseEnvFile(string $filePath): array
    {
        $content = @file_get_contents($filePath);

        if ($content === false) {
            throw EnvLoaderException::fileNotReadable($filePath);
        }

        $vars = [];
        $lines = explode("\n", $content);

        foreach ($lines as $lineNumber => $line) {
            $line = trim($line);

            // Skip empty lines and comments
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            // Parse variable assignment
            $result = $this->parseEnvLine($line, $lineNumber + 1, $filePath);
            if ($result !== null) {
                [$key, $value] = $result;
                $vars[$key] = $value;
            }
        }

        return $vars;
    }

    /**
     * Parse a single line from .env file into key-value pair
     *
     * Validates the line format, extracts variable name and value,
     * and performs proper quote parsing with escape sequence handling.
     *
     * @param string $line Line content to parse
     * @param int $lineNumber Line number for error reporting (1-based)
     * @param string $filePath File path for error reporting
     * @return array|null [key, value] pair or null if line should be skipped
     * @throws EnvLoaderException If line format is invalid or variable name is malformed
     */
    private function parseEnvLine(string $line, int $lineNumber, string $filePath): ?array
    {
        // Check for variable assignment pattern
        if (!str_contains($line, '=')) {
            throw EnvLoaderException::parseError($filePath, $lineNumber, $line);
        }

        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            throw EnvLoaderException::parseError($filePath, $lineNumber, $line);
        }

        [$key, $value] = $parts;
        $key = trim($key);

        // Validate variable name according to POSIX standards
        if (!$this->isValidVariableName($key)) {
            throw EnvLoaderException::parseError($filePath, $lineNumber, $line);
        }

        // Parse value with quote handling and escape sequences
        $value = $this->parseValue(trim($value));

        return [$key, $value];
    }

    /**
     * Parse environment variable value with quote and escape handling
     *
     * Handles various value formats:
     * - Unquoted values: returned as-is
     * - Single-quoted values: no escape sequence processing
     * - Double-quoted values: escape sequence processing applied
     *
     * @param string $value Raw value from .env file
     * @return string Parsed value with quotes removed and escapes processed
     */
    private function parseValue(string $value): string
    {
        if (empty($value)) {
            return '';
        }

        $length = strlen($value);
        $firstChar = $value[0];
        $lastChar = $value[$length - 1];

        // Handle quoted values (must have matching quotes at start and end)
        if ($length >= 2 && (
                ($firstChar === '"' && $lastChar === '"') ||
                ($firstChar === "'" && $lastChar === "'")
            )) {
            $value = substr($value, 1, -1);

            // Handle escape sequences in double quotes only
            if ($firstChar === '"') {
                $value = $this->unescapeDoubleQuotedValue($value);
            }
        }

        return $value;
    }

    /**
     * Process escape sequences in double-quoted values
     *
     * Converts escape sequences to their actual characters:
     * - \n → newline
     * - \r → carriage return
     * - \t → tab
     * - \" → double quote
     * - \\ → backslash
     *
     * @param string $value Value to process escape sequences in
     * @return string Value with escape sequences converted to actual characters
     */
    private function unescapeDoubleQuotedValue(string $value): string
    {
        return str_replace(
            ['\\n', '\\r', '\\t', '\\"', '\\\\'],
            ["\n", "\r", "\t", '"', '\\'],
            $value
        );
    }

    /**
     * Validate environment variable name according to POSIX standards
     *
     * Variable names must:
     * - Not be empty
     * - Start with a letter or underscore
     * - Contain only letters, numbers, and underscores
     *
     * @param string $name Variable name to validate
     * @return bool True if the variable name is valid, false otherwise
     */
    private function isValidVariableName(string $name): bool
    {
        return !empty($name) && preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name);
    }

    /**
     * Populate environment superglobals with loaded variables
     *
     * Sets variables in $_ENV and optionally $_SERVER superglobals.
     * Respects the override setting to determine whether to overwrite
     * existing environment variables.
     *
     * @param array $vars Variables to populate in environment
     */
    private function populateEnvironment(array $vars): void
    {
        foreach ($vars as $key => $value) {
            // Only set if not exists or override is enabled
            if ($this->override || !isset($_ENV[$key])) {
                $_ENV[$key] = $value;

                if ($this->populateServer) {
                    $_SERVER[$key] = $value;
                }
            }
        }
    }
}