<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py;

use JesseGall\CodeCommandments\LocatedQuery;

/**
 * Fluent query over Python expressions — `[expression, module]` pairs, narrowed with `where`/`reject`.
 */
final class ExprQuery extends LocatedQuery
{
    /**
     * Keep calls to one of $names, as {@see ExprMatch::callName} spells them.
     */
    public function calling(string ...$names): self
    {
        return $this->filter(static fn (ExprMatch $match): bool => in_array($match->callName(), $names, true));
    }

    protected function wrap(mixed $candidate, ?string $as): object
    {
        [$expr, $module] = $candidate;
        $class = $as ?? ExprMatch::class;

        return new $class($expr, $module);
    }

    protected function matchClass(): string
    {
        return ExprMatch::class;
    }
}
