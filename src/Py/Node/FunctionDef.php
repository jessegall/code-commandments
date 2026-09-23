<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py\Node;

use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\PhpTypes\Option;

/**
 * A `def` — a module function or a method alike — with its decorators, parameters, return annotation
 * and body.
 */
final class FunctionDef extends Node
{
    /**
     * @param  list<Param>  $params
     * @param  list<Expr>  $decorators
     */
    public function __construct(
        public readonly string $name,
        public readonly array $params,
        public readonly Block $body,
        public readonly ?Expr $returns = null,
        public readonly array $decorators = [],
        public readonly bool $async = false,
    ) {}

    public function children(): array
    {
        return [...$this->params, $this->body];
    }

    public function expressions(): array
    {
        return $this->decorators;
    }

    public function declaredNames(): array
    {
        return [$this->name];
    }

    public function functionBody(): Option
    {
        return Option::some($this->body);
    }
}
