<?php

namespace Bermuda\Config;

final class ProviderException extends \Exception
{
    public function __construct(
        public readonly mixed $provider,
        array $backtrace
    ) {
        parent::__construct($this->generateMessage());

        $this->file = $backtrace['file'];
        $this->line = $backtrace['line'];
    }

    private function generateMessage(): string
    {
        if (is_string($this->provider)) return 'Invalid provider passed: ' . $this->provider;
        if (is_object($this->provider)) return 'Invalid provider passed: ' . $this->provider::class;

        return 'Invlaid provider passed';
    }
}