<?php

namespace Bermuda\Config;

use Bermuda\Config\Reader\FileReaderInterface;
use Bermuda\Config\Reader\JsonFileReader;
use Bermuda\Config\Reader\PhpFileReader;
use Bermuda\Config\Reader\YamlFileReader;
use Bermuda\Config\Reader\CsvFileReader;
use Bermuda\Config\Reader\XmlFileReader;
use Laminas\ConfigAggregator\GlobTrait;

/**
 * File-based configuration provider
 *
 * Loads configuration from multiple files matching a glob pattern.
 * Supports PHP, JSON, YAML, CSV (with auto-detection), and XML files through configurable readers.
 * Files are processed in the order they are found and merged recursively.
 *
 * Default readers include:
 * - PhpFileReader: .php files returning arrays
 * - YamlFileReader: .yaml/.yml files
 * - JsonFileReader: .json files
 * - CsvFileReader: .csv files with auto-detected delimiters
 * - XmlFileReader: .xml files optimized for configuration
 */
final class FileProvider
{
    use GlobTrait;

    /**
     * @var FileReaderInterface[] Array of file readers for different formats
     */
    private array $readers = [];

    /**
     * Constructor for FileProvider
     *
     * @param string $pattern Glob pattern to match configuration files (e.g., 'config/*.php')
     * @param iterable<FileReaderInterface>|null $readers Custom file readers, defaults to PHP/JSON/YAML/CSV/XML
     */
    public function __construct(
        protected readonly string $pattern,
        ?iterable $readers = null
    ) {
        if (!$readers) {
            $readers = [
                new PhpFileReader,
                new YamlFileReader,
                new JsonFileReader,
                CsvFileReader::createWithAutoDetection(),
                XmlFileReader::createForConfig(),
            ];
        }

        $this->readers = is_array($readers) ? $readers : iterator_to_array($readers);
    }

    /**
     * Load and merge configuration from all matching files
     *
     * Iterates through files matching the glob pattern and attempts to read
     * each file with available readers. First successful read wins for each file.
     * All loaded configurations are merged recursively.
     *
     * @return array Merged configuration array from all matching files
     */
    public function __invoke(): array
    {
        $config = [];
        foreach ($this->glob($this->pattern) as $file) {
            foreach ($this->readers as $reader) {
                $data = $reader->readFile($file);
                if ($data) {
                    $config = array_merge_recursive($config, $data);
                    continue 2; // Move to next file
                }
            }
        }

        return $config;
    }

    /**
     * Add a custom file reader to the readers list
     *
     * @param FileReaderInterface $reader File reader to add
     */
    public function addReader(FileReaderInterface $reader): self
    {
        $this->readers[] = $reader;
        return $this;
    }
}