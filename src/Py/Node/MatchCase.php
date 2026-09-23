<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py\Node;

use JesseGall\CodeCommandments\Py\Expr\Expr;

/**
 * One `case pattern if guard:` of a match. The pattern is read as an expression, which a class,
 * sequence or literal pattern is written as.
 */
final class MatchCase extends Node
{
    public function __construct(
        public readonly Expr $pattern,
        public readonly ?Expr $guard,
        public readonly Block $body,
    ) {}

    public function children(): array
    {
        return [$this->body];
    }

    public function expressions(): array
    {
        return self::present([$this->pattern, $this->guard]);
    }

    /**
     * Is this `case _:` — the arm every value no other case took falls into?
     */
    public function isWildcard(): bool
    {
        return $this->pattern->dottedName() === '_' && $this->guard === null;
    }

    /**
     * Does this arm end by returning nothing — `return`, `return None`, `return False`, an empty value?
     */
    public function isAnswerAbsent(): bool
    {
        $body = $this->body->body;

        return $body !== [] && end($body)->returnsAbsence();
    }
}
