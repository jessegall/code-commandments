<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py\Node;

use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\Expr\ExprKind;

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

    public function writtenTargets(): array
    {
        return $this->targets;
    }

    public function declaresConstant(): bool
    {
        return array_all($this->targets, static fn (Expr $target): bool => $target->is(ExprKind::Name) && preg_match('/^_*[A-Z][A-Z0-9_]*$/', (string) $target->get('name')) === 1);
    }

    public function isStateDeclaration(): bool
    {
        return true;
    }
}
