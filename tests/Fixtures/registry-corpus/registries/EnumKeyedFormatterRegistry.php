<?php

namespace App\RegistryCorpus;

/**
 * REGISTRY: yes — you register a Formatter under a Format enum case and later
 * look one up by the same enum; it is a keyed put/get store with no behaviour
 * of its own beyond holding the mapping.
 */
class EnumKeyedFormatterRegistry
{
    /** @var array<string, Formatter> */
    private array $byFormat = [];

    public function register(Format $format, Formatter $formatter): void
    {
        $this->byFormat[$format->value] = $formatter;
    }

    public function get(Format $format): Formatter
    {
        return $this->byFormat[$format->value]
            ?? throw new \OutOfBoundsException("No formatter registered for {$format->value}.");
    }

    public function has(Format $format): bool
    {
        return isset($this->byFormat[$format->value]);
    }
}
