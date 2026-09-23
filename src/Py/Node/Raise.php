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
    /**
     * The builtins that say only "something failed".
     */
    private const array GENERIC = ['Exception', 'BaseException', 'RuntimeError'];

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

    /**
     * Does this raise a builtin that names no failure — `Exception`, `BaseException`, `RuntimeError` —
     * with the failure described in a message written at the raise? Python's specific builtins
     * (`ValueError`, `TypeError`, `KeyError`, …) name a category a caller catches by, and are not this.
     */
    public function isGenericWithMessage(): bool
    {
        if ($this->exception === null || ! $this->exception->isCall()) {
            return false;
        }

        $message = $this->exception->get('arguments')[0] ?? null;

        return in_array($this->exception->get('callee')->dottedName(), self::GENERIC, true)
            && $message instanceof Expr
            && ($message->is(ExprKind::FString) || $message->literalType()?->isText() === true);
    }
}
