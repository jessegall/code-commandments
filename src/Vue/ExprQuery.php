<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

use Closure;
use JesseGall\CodeCommandments\LocatedQuery;
use JesseGall\CodeCommandments\Vue\Expr\Expr;

/**
 * Fluent query over EXPRESSION space — every call, member read, comparison and default in a
 * codebase's TypeScript, each knowing its own `file:line`.
 */
final class ExprQuery extends LocatedQuery
{
    /**
     * Keep calls to $names — `querySelector`, `closest`. Matches the full callee as written, so both
     * `closest` and `node.closest` can be asked for.
     */
    public function calling(string ...$names): self
    {
        return $this->filter(static fn (ExprMatch $match): bool => in_array($match->expr->callName(), $names, true));
    }

    /**
     * Keep expressions whose callee ENDS with $method — `node.closest(…)` for `closest`, whatever
     * the receiver is called.
     */
    public function callingMethod(string $method): self
    {
        return $this->filter(static fn (ExprMatch $match): bool => str_ends_with($match->expr->callName(), '.' . $method));
    }

    public function inPath(string $fragment): self
    {
        return $this->filter(static fn (ExprMatch $match): bool => str_contains($match->file(), $fragment));
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
