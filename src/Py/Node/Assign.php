<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py\Node;

use JesseGall\CodeCommandments\Py\Expr\Expr;

/**
 * `a = b = value` — every target the value is bound to, then the value.
 */
final class Assign extends Node
{
    /**
     * @param  list<Expr>  $targets
     */
    public function __construct(
        public readonly array $targets,
        public readonly Expr $value,
    ) {}

    public function expressions(): array
    {
        return [...$this->targets, $this->value];
    }
}
