<?php

namespace Bermuda\Config\Reader;

/**
 * CSV file reader for tabular configuration data
 *
 * Reads CSV files and converts them to associative arrays.
 * Supports different delimiters, enclosures, and data type conversion.
 * First row is treated as headers by default.
 */
final class CsvFileReader implements FileReaderInterface
{
    private string $delimiter;
    private string $enclosure;
    private string $escape;
    private bool $hasHeaders;
    private bool $autoDetectTypes;
    private bool $autoDetectDelimiter;

    /**
     * Constructor for CSV file reader
     *
     * @param string $delimiter Field delimiter (default: comma)
     * @param string $enclosure Field enclosure character (default: double quote)
     * @param string $escape Escape character (default: backslash)
     * @param bool $hasHeaders Whether first row contains headers (default: true)
     * @param bool $autoDetectTypes Whether to auto-detect and convert data types (default: true)
     * @param bool $autoDetectDelimiter Whether to auto-detect delimiter (default: false)
     */
    public function __construct(
        string $delimiter = ',',
        string $enclosure = '"',
        string $escape = '\\',
        bool $hasHeaders = true,
        bool $autoDetectTypes = true,
        bool $autoDetectDelimiter = false
    ) {
        $this->delimiter = $delimiter;
        $this->enclosure = $enclosure;
        $this->escape = $escape;
        $this->hasHeaders = $hasHeaders;
        $this->autoDetectTypes = $autoDetectTypes;
        $this->autoDetectDelimiter = $autoDetectDelimiter;
    }

    /**
     * Read and parse a CSV configuration file
     *
     * @param string $file Path to the CSV file
     * @return array|null Parsed CSV data as array or null if not supported/failed
     */
    public function readFile(string $file): ?array
    {
        if (!str_ends_with($file, '.csv')) {
            return null;
        }

        $handle = fopen($file, 'r');
        if ($handle === false) {
            return null;
        }

        try {
            $delimiter = $this->delimiter;

            // Auto-detect delimiter if enabled
            if ($this->autoDetectDelimiter) {
                $delimiter = $this->detectDelimiter($handle);
                // Reset file pointer after detection
                rewind($handle);
            }

            $data = [];
            $headers = null;
            $rowIndex = 0;

            while (($row = fgetcsv($handle, 0, $delimiter, $this->enclosure, $this->escape)) !== false) {
                // Skip empty rows
                if (empty(array_filter($row, fn($cell) => trim($cell) !== ''))) {
                    continue;
                }

                if ($this->hasHeaders && $headers === null) {
                    // First row as headers
                    $headers = array_map('trim', $row);
                } else {
                    if ($this->hasHeaders && $headers !== null) {
                        // Create associative array with headers as keys
                        $rowData = [];
                        foreach ($row as $index => $value) {
                            $key = $headers[$index] ?? "column_$index";
                            $rowData[$key] = $this->autoDetectTypes ? $this->convertValue(trim($value)) : trim($value);
                        }
                        $data[] = $rowData;
                    } else {
                        // No headers - use numeric indices
                        $rowData = [];
                        foreach ($row as $index => $value) {
                            $rowData[$index] = $this->autoDetectTypes ? $this->convertValue(trim($value)) : trim($value);
                        }
                        $data[] = $rowData;
                    }
                }
                $rowIndex++;
            }

            return $data;

        } catch (\Throwable $e) {
            return null;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Detect delimiter by analyzing file content
     *
     * @param resource $handle File handle positioned at start of file
     * @return string Detected delimiter
     */
    private function detectDelimiter($handle): string
    {
        $sample = '';
        $position = ftell($handle);

        // Read first few lines to detect delimiter
        for ($i = 0; $i < 5; $i++) {
            $line = fgets($handle);
            if ($line === false) break;
            $sample .= $line;
        }

        // Reset file pointer
        fseek($handle, $position);

        // Try different delimiters and count occurrences
        $delimiters = [',', ';', "\t", '|'];
        $counts = [];

        foreach ($delimiters as $delimiter) {
            $counts[$delimiter] = substr_count($sample, $delimiter);
        }

        // Use the delimiter that appears most frequently
        $maxCount = max($counts);
        if ($maxCount === 0) {
            return $this->delimiter; // Fallback to default
        }

        return array_search($maxCount, $counts);
    }

    /**
     * Convert string value to appropriate PHP type
     *
     * @param string $value String value to convert
     * @return mixed Converted value (string, int, float, bool, or null)
     */
    private function convertValue(string $value): mixed
    {
        // Handle empty values
        if ($value === '') {
            return null;
        }

        // Handle null values
        if (strtolower($value) === 'null') {
            return null;
        }

        // Handle boolean values
        $lowerValue = strtolower($value);
        if (in_array($lowerValue, ['true', 'yes', '1', 'on'])) {
            return true;
        }
        if (in_array($lowerValue, ['false', 'no', '0', 'off'])) {
            return false;
        }

        // Handle numeric values
        if (is_numeric($value)) {
            // Check if it's an integer
            if (ctype_digit($value) || (str_starts_with($value, '-') && ctype_digit(substr($value, 1)))) {
                $intValue = (int)$value;
                // Make sure we didn't lose precision
                if ((string)$intValue === $value) {
                    return $intValue;
                }
            }
            // Return as float
            return (float)$value;
        }

        // Return as string
        return $value;
    }

    /**
     * Create a CSV reader with auto-detection enabled
     *
     * @param bool $hasHeaders Whether first row contains headers
     * @param bool $autoDetectTypes Whether to auto-detect data types
     * @return self CSV reader with auto-detection enabled
     */
    public static function createWithAutoDetection(bool $hasHeaders = true, bool $autoDetectTypes = true): self
    {
        return new self(
            delimiter: ',', // Will be auto-detected
            hasHeaders: $hasHeaders,
            autoDetectTypes: $autoDetectTypes,
            autoDetectDelimiter: true
        );
    }
}