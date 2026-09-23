<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py;

use JesseGall\CodeCommandments\LocatedQuery;

/**
 * Fluent query over Python nodes — the same `where`/`reject` loop every engine's query runs, over
 * `[node, module]` pairs.
 */
final class Query extends LocatedQuery
{
    /**
     * Keep nodes whose declared name is one of $names.
     */
    public function nameIs(string ...$names): self
    {
        return $this->filter(static fn (NodeMatch $match): bool => in_array($match->name(), $names, true));
    }

    protected function wrap(mixed $candidate, ?string $as): object
    {
        [$node, $module] = $candidate;
        $class = $as ?? NodeMatch::class;

        return new $class($node, $module);
    }

    protected function matchClass(): string
    {
        return NodeMatch::class;
    }
}
