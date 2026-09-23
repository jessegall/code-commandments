<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py\Node;

use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\Expr\LiteralType;

/**
 * One parameter of a function: its name, its annotation and default when written, and its kind — `*`
 * for `*args`, `**` for `**kwargs`, empty for an ordinary one.
 */
final class Param extends Node
{
    public function __construct(
        public readonly string $name,
        public readonly string $kind = '',
        public readonly ?Expr $annotation = null,
        public readonly ?Expr $default = null,
    ) {}

    public function expressions(): array
    {
        return self::present([$this->default]);
    }

    public function variant(): string
    {
        return $this->kind;
    }

    public function declaredNames(): array
    {
        return [$this->name];
    }

    /**
     * Does $test choose on this parameter alone — `flag` or `not flag` for a `bool`, `x is None` for one
     * that is optional and left out by default?
     */
    public function isSelectedBy(Expr $test): bool
    {
        return ($this->annotation?->dottedName() === 'bool' && $test->testsFlag($this->name))
            || ($this->isOptional() && $test->testsNoneOf($this->name));
    }

    /**
     * Is this typed `X | None` and left out as `None` by default?
     */
    public function isOptional(): bool
    {
        return $this->default?->literalType() === LiteralType::None
            && ($this->annotation?->optionalOf()->isSome() ?? false);
    }
}
