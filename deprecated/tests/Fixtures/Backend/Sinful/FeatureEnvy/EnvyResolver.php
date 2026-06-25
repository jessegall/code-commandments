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

    public function toDtos(NodeDescriptor $descriptor): array
    {
        // array_map building *Data DTOs from the foreign collection is a
        // presentation MAPPER, not feature envy — must NOT be flagged.
        return array_map(static fn (object $o): object => OutputSocketData::fromSocket($o), $descriptor->outputs);
    }

    public function summarise(NodeDescriptor $descriptor): array
    {
        // Reading $descriptor->toArray() is a serialization boundary, not a
        // reach into internals — must NOT be flagged.
        return array_map(static fn (mixed $v): string => (string) $v, $descriptor->toArray());
    }
}
