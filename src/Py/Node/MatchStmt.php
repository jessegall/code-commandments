<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py\Node;

use JesseGall\CodeCommandments\Py\Expr\Expr;

/**
 * A `match subject:` with its cases.
 */
final class MatchStmt extends Node
{
    /**
     * @param  list<MatchCase>  $cases
     */
    public function __construct(
        public readonly Expr $subject,
        public readonly array $cases,
    ) {}

    public function children(): array
    {
        return $this->cases;
    }

    public function expressions(): array
    {
        return [$this->subject];
    }
}
