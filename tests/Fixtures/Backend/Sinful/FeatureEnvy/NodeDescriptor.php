<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Sinful\FeatureEnvy;

/**
 * The OWNER (project-owned domain object). Querying its OWN collection from
 * here is fine — that is exactly where the query belongs.
 */
class NodeDescriptor
{
    /** @var list<object> */
    public array $outputs = [];

    public function findOutputHere(string $port): mixed
    {
        // Over $this->outputs — own data, must NOT be flagged.
        return Option::first($this->outputs, fn (object $o): bool => $o->hasName($port));
    }

    /** @return list<string> */
    public function continuationHandleNames(): array
    {
        return [];
    }

    /** @return list<string> */
    public function bodyHandleNames(): array
    {
        return [];
    }
}
