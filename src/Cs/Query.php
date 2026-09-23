<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cs;

use JesseGall\CodeCommandments\LocatedQuery;

/**
 * A fluent query over C# nodes — the shared {@see LocatedQuery} surface every engine reads through.
 */
final class Query extends LocatedQuery
{
    public function nameIs(string ...$names): self
    {
        return $this->filter(static fn (NodeMatch $match): bool => in_array($match->name(), $names, true));
    }

    public function kindIs(string ...$kinds): self
    {
        return $this->filter(static fn (NodeMatch $match): bool => $match->node->is(...$kinds));
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
