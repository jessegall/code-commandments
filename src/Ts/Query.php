<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ts;

use Closure;
use JesseGall\CodeCommandments\LocatedQuery;
use JesseGall\CodeCommandments\Ts\Node\Node;

/**
 * Fluent query over TypeScript module space — the same `where`/`reject` loop {@see Query} runs over
 * template elements, just over {@see Node}s. A `.ts` rule composes exactly what a `.vue` one does.
 */
final class Query extends LocatedQuery
{
    /**
     * Keep nodes declaring a name at all — a function, a class, a parameter, never a bare statement.
     */
    public function named(): self
    {
        return $this->filter(static fn (NodeMatch $match): bool => $match->name() !== '');
    }

    /**
     * Keep nodes whose declared name is one of $names.
     */
    public function nameIs(string ...$names): self
    {
        return $this->filter(static fn (NodeMatch $match): bool => in_array($match->name(), $names, true));
    }

    /**
     * Keep nodes sitting in a file whose path contains $fragment — the cheap way to scope a rule to
     * one area of a client without a second selector.
     */
    public function inPath(string $fragment): self
    {
        return $this->filter(static fn (NodeMatch $match): bool => str_contains($match->file(), $fragment));
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
