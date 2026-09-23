<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py\Node;

use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\Expr\ExprKind;

/**
 * `limit: int = 10` — a target, its annotation, and the value when one is given.
 */
final class AnnAssign extends Node
{
    public function __construct(
        public readonly Expr $target,
        public readonly Expr $annotation,
        public readonly ?Expr $value = null,
    ) {}

    public function expressions(): array
    {
        return self::present([$this->target, $this->value]);
    }

    public function writtenTargets(): array
    {
        return $this->value === null ? [] : [$this->target];
    }

    public function declaresConstant(): bool
    {
        $named = $this->annotation->is(ExprKind::Subscript) ? $this->annotation->get('object') : $this->annotation;

        return in_array($named->dottedName(), ['Final', 'typing.Final', 'ClassVar', 'typing.ClassVar'], true)
            || ($this->target->is(ExprKind::Name) && preg_match('/^_*[A-Z][A-Z0-9_]*$/', (string) $this->target->get('name')) === 1);
    }

    public function isStateDeclaration(): bool
    {
        return true;
    }
}
