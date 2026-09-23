<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py\Node;

use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\Expr\ExprKind;

/**
 * A `raise` — of an exception and the cause it is raised `from`, or bare, re-raising the one in hand.
 */
final class Raise extends Node
{
    public function __construct(
        public readonly ?Expr $exception = null,
        public readonly ?Expr $cause = null,
    ) {}

    public function expressions(): array
    {
        return self::present([$this->exception, $this->cause]);
    }

    /**
     * `raise NotImplementedError`, bare or called — the body an abstract or protocol method leaves to
     * its subclasses.
     */
    public function isPlaceholder(): bool
    {
        if ($this->exception === null) {
            return false;
        }

        $raised = $this->exception->isCall() ? $this->exception->get('callee') : $this->exception;

        return $raised->is(ExprKind::Name) && $raised->get('name') === 'NotImplementedError';
    }

    public function isBailOut(): bool
    {
        return true;
    }
}
