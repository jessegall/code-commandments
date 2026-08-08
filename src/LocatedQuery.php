<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

use Closure;

/**
 * A {@see Query} whose candidates are a flat list of `[subject, source]` pairs — a template element
 * and its component, a TypeScript node and its module, an expression and the module holding it. The
 * selector loop every frontend query shares lives here once, so a subclass says only how a pair
 * becomes a match ({@see wrap}) and which class its decorators extend ({@see matchClass}).
 */
abstract class LocatedQuery extends Query
{
    /**
     * @param  Closure(): list<array{0: mixed, 1: mixed}>  $candidates  the pairs this query draws from
     * @param  Closure(mixed): bool  $selector  applied to the SUBJECT of each pair
     */
    public function __construct(
        private readonly Closure $candidates,
        private readonly Closure $selector,
    ) {}

    protected function selected(): iterable
    {
        foreach (($this->candidates)() as $candidate) {
            if (($this->selector)($candidate[0])) {
                yield $candidate;
            }
        }
    }
}
