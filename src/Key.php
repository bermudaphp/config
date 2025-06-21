<?php

namespace Bermuda\Config;

/**
 * Key wrapper for literal keys containing dots
 *
 * This class allows you to work with configuration keys that contain dots
 * as literal characters rather than dot notation separators.
 *
 * Example:
 * - Normal dot notation: 'database.host' -> accesses $config['database']['host']
 * - Literal key: Key::literal('database.host') -> accesses $config['database.host']
 */
final readonly class Key implements \Stringable
{
    /**
     * Constructor for Key wrapper
     *
     * @param string $value The literal key value
     */
    public function __construct(
        public string $value
    ) {
    }

    /**
     * Create a literal key wrapper
     *
     * @param string $key The literal key containing dots
     * @return self Key wrapper instance
     */
    public static function literal(string $key): self
    {
        return new self($key);
    }

    /**
     * String representation of the key
     *
     * @return string The literal key value
     */
    public function __toString(): string
    {
        return $this->value;
    }
}