<?php

namespace Bermuda\Config\Reader;

/**
 * XML file reader for configuration files
 *
 * Reads and parses XML configuration files (.xml extension).
 * Converts XML structure to associative arrays with support for attributes.
 */
final class XmlFileReader implements FileReaderInterface
{
    private bool $includeAttributes;
    private string $attributePrefix;
    private bool $parseNamespaces;
    private bool $removeNamespaces;

    /**
     * Constructor for XML file reader
     *
     * @param bool $includeAttributes Whether to include XML attributes in output (default: true)
     * @param string $attributePrefix Prefix for attribute keys (default: '@')
     * @param bool $parseNamespaces Whether to parse XML namespaces (default: false)
     * @param bool $removeNamespaces Whether to remove namespace prefixes from element names (default: true)
     */
    public function __construct(
        bool $includeAttributes = true,
        string $attributePrefix = '@',
        bool $parseNamespaces = false,
        bool $removeNamespaces = true
    ) {
        $this->includeAttributes = $includeAttributes;
        $this->attributePrefix = $attributePrefix;
        $this->parseNamespaces = $parseNamespaces;
        $this->removeNamespaces = $removeNamespaces;
    }

    /**
     * Read and parse an XML configuration file
     *
     * @param string $file Path to the XML file
     * @return array|null Parsed XML data as array or null if not supported/failed
     */
    public function readFile(string $file): ?array
    {
        if (!str_ends_with($file, '.xml')) {
            return null;
        }

        try {
            $content = file_get_contents($file);
            if ($content === false) {
                return null;
            }

            // Clear any previous XML errors
            libxml_clear_errors();

            // Use internal error handling
            $useInternalErrors = libxml_use_internal_errors(true);

            try {
                $xml = simplexml_load_string(
                    $content,
                    'SimpleXMLElement',
                    LIBXML_NOCDATA | ($this->parseNamespaces ? 0 : LIBXML_NONET)
                );

                if ($xml === false) {
                    return null;
                }

                $result = $this->xmlToArray($xml);

                return is_array($result) ? $result : null;

            } finally {
                // Restore previous error handling setting
                libxml_use_internal_errors($useInternalErrors);
            }

        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Convert SimpleXMLElement to array recursively
     *
     * @param \SimpleXMLElement $xml XML element to convert
     * @return mixed Converted array, string, or null
     */
    private function xmlToArray(\SimpleXMLElement $xml): mixed
    {
        $array = [];

        // Handle attributes
        if ($this->includeAttributes) {
            foreach ($xml->attributes() as $name => $value) {
                $attributeName = $this->attributePrefix . $name;
                $array[$attributeName] = $this->convertValue((string)$value);
            }

            // Handle namespaced attributes if parsing namespaces
            if ($this->parseNamespaces) {
                foreach ($xml->attributes('', true) as $namespace => $attributes) {
                    foreach ($attributes as $name => $value) {
                        $attributeName = $this->attributePrefix .
                            ($this->removeNamespaces ? $name : "$namespace:$name");
                        $array[$attributeName] = $this->convertValue((string)$value);
                    }
                }
            }
        }

        // Handle child elements
        $children = $xml->children();
        $hasChildren = count($children) > 0;

        if ($hasChildren) {
            foreach ($children as $name => $child) {
                $elementName = $this->removeNamespaces ? $this->removeNamespacePrefix($name) : $name;

                // Handle multiple elements with the same name
                if (isset($array[$elementName])) {
                    // Convert to indexed array if not already
                    if (!is_array($array[$elementName]) || !isset($array[$elementName][0])) {
                        $array[$elementName] = [$array[$elementName]];
                    }
                    $array[$elementName][] = $this->xmlToArray($child);
                } else {
                    $array[$elementName] = $this->xmlToArray($child);
                }
            }
        }

        // Handle text content
        $text = trim((string)$xml);
        if ($text !== '') {
            if (empty($array)) {
                // Only text content, return converted value
                return $this->convertValue($text);
            } else {
                // Mixed content - add text as special key
                $array['_text'] = $this->convertValue($text);
            }
        }

        // Return array or null for empty elements
        return empty($array) ? null : $array;
    }

    /**
     * Remove namespace prefix from element name
     *
     * @param string $name Element name potentially with namespace prefix
     * @return string Element name without namespace prefix
     */
    private function removeNamespacePrefix(string $name): string
    {
        $pos = strpos($name, ':');
        return $pos !== false ? substr($name, $pos + 1) : $name;
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

        // Handle explicit null
        if (strtolower($value) === 'null') {
            return null;
        }

        // Handle boolean values
        $lowerValue = strtolower($value);
        if (in_array($lowerValue, ['true', 'yes', '1'])) {
            return true;
        }
        if (in_array($lowerValue, ['false', 'no', '0'])) {
            return false;
        }

        // Handle numeric values
        if (is_numeric($value)) {
            // Check if it's an integer
            if (ctype_digit($value) || (str_starts_with($value, '-') && ctype_digit(substr($value, 1)))) {
                return (int)$value;
            }
            // Return as float
            return (float)$value;
        }

        // Return as string
        return $value;
    }

    /**
     * Create XML reader optimized for configuration files
     *
     * @return self XML reader with configuration-friendly settings
     */
    public static function createForConfig(): self
    {
        return new self(
            includeAttributes: true,
            attributePrefix: '@',
            parseNamespaces: false,
            removeNamespaces: true
        );
    }

    /**
     * Create XML reader optimized for data transformation
     *
     * @return self XML reader optimized for preserving XML structure
     */
    public static function createForData(): self
    {
        return new self(
            includeAttributes: false,
            attributePrefix: '@',
            parseNamespaces: false,
            removeNamespaces: true
        );
    }
}