<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py\Node;

use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\Expr\ExprKind;

/**
 * One `except` clause: the exception type it catches (none for a bare `except:`), the name it binds,
 * and its block. `except*` catches from an exception group.
 */
final class ExceptHandler extends Node
{
    /**
     * The builtin exceptions every failure descends from — catching one catches everything.
     */
    private const array ROOTS = ['Exception', 'BaseException'];

    public function __construct(
        public readonly ?Expr $type,
        public readonly ?string $name,
        public readonly Block $body,
        public readonly bool $group = false,
    ) {}

    public function children(): array
    {
        return [$this->body];
    }

    public function expressions(): array
    {
        return self::present([$this->type]);
    }

    public function variant(): string
    {
        return $this->group ? 'except*' : 'except';
    }

    /**
     * Does this handler catch everything — a bare `except:`, or `Exception`/`BaseException`, alone or in
     * a tuple — rather than a failure it names?
     */
    public function isBroad(): bool
    {
        if ($this->type === null) {
            return true;
        }

        $types = $this->type->is(ExprKind::Tuple) ? $this->type->get('elements') : [$this->type];

        return array_any($types, static fn (Expr $type): bool => in_array($type->dottedName(), self::ROOTS, true));
    }

    /**
     * Does this handler's body make the failure vanish — nothing but `pass`, `...`, `continue`, or a
     * `return` of nothing or of an empty value?
     */
    public function swallows(): bool
    {
        if (count($this->body->body) !== 1) {
            return false;
        }

        return $this->body->body[0]->isNoOp();
    }
}
