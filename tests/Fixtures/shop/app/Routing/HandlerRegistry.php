<?php

namespace Shop\Routing;

use JesseGall\CodeCommandments\Detectors\Backend\NullableRegistryLookupDetector;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Resolves an event name to its handler, returning null when nothing is
 * registered — a lookup that should throw on an unknown event.
 */
final class HandlerRegistry
{
    /** @var array<string, callable> */
    private array $handlers = [];

    /** @var array<string, string> */
    private array $aliases = [];

    public function register(string $event, callable $handler): void
    {
        $this->handlers[$event] = $handler;
    }

    public function alias(string $from, string $to): void
    {
        $this->aliases[$from] = $to;
    }

    #[Sinful(NullableRegistryLookupDetector::class)]
    public function for(string $event): ?callable
    {
        foreach ($this->aliases as $alias => $canonical) {
            if ($alias !== $event) {
                continue;
            }

            $event = $canonical;
        }

        return $this->handlers[$event] ?? null;
    }
}
