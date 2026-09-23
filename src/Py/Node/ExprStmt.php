<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py\Node;

use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\Expr\LiteralType;

/**
 * An expression standing as a statement — a call made for its effect, a docstring.
 */
final class ExprStmt extends Node
{
    public function __construct(public readonly Expr $value) {}

    public function expressions(): array
    {
        return [$this->value];
    }

    public function isExpressionStatement(): bool
    {
        return true;
    }

    public function isPlaceholder(): bool
    {
        return $this->isBareString() || $this->value->literalType() === LiteralType::Ellipsis;
    }

    /**
     * Is this a string standing alone — a docstring, or text left in the code — which runs nothing?
     */
    public function isBareString(): bool
    {
        return $this->value->literalType()?->isText() === true;
    }
}
