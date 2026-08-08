<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

use Closure;
use JesseGall\CodeCommandments\LocatedQuery;
use JesseGall\CodeCommandments\Vue\Ts\Node\Node;

/**
 * Fluent query over TypeScript module space — the same `where`/`reject` loop {@see Query} runs over
 * template elements, just over {@see Node}s. A `.ts` rule composes exactly what a `.vue` one does.
 */
final class TsQuery extends LocatedQuery
{
    /**
     * Keep nodes declaring a name at all — a function, a class, a parameter, never a bare statement.
     */
    public function named(): self
    {
        return $this->filter(static fn (TsMatch $match): bool => $match->name() !== '');
    }

    /**
     * Keep nodes whose declared name is one of $names.
     */
    public function nameIs(string ...$names): self
    {
        return $this->filter(static fn (TsMatch $match): bool => in_array($match->name(), $names, true));
    }

    /**
     * Keep nodes sitting in a file whose path contains $fragment — the cheap way to scope a rule to
     * one area of a client without a second selector.
     */
    public function inPath(string $fragment): self
    {
        return $this->filter(static fn (TsMatch $match): bool => str_contains($match->file(), $fragment));
    }

    protected function wrap(mixed $candidate, ?string $as): object
    {
        [$node, $module] = $candidate;
        $class = $as ?? TsMatch::class;

        return new $class($node, $module);
    }

    protected function matchClass(): string
    {
        return TsMatch::class;
    }
}
