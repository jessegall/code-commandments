<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Sinful\FeatureEnvy;

/**
 * The ASKER — reaches into NodeDescriptor's internals and runs queries that
 * belong ON NodeDescriptor. Both methods are feature envy.
 */
class EnvyResolver
{
    public function findOutput(NodeDescriptor $descriptor, string $port): mixed
    {
        return Option::first($descriptor->outputs, fn (object $o): bool => $o->hasName($port));
    }

    public function isControlHandle(NodeDescriptor $descriptor, string $port): bool
    {
        return in_array($port, $descriptor->continuationHandleNames(), true)
            || in_array($port, $descriptor->bodyHandleNames(), true);
    }

    public function ownWork(string $port): string
    {
        // Touches no foreign object — must NOT be flagged.
        $value = strtoupper($port);

        return $value . '!';
    }
}
