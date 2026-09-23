<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py\Expr;

use JesseGall\CodeCommandments\ExpressionTree;
use JesseGall\CodeCommandments\Positioned;

/**
 * A node of a parsed Python expression — a kind and the properties that kind carries, shaped like the
 * TypeScript engine's {@see \JesseGall\CodeCommandments\Ts\Expr\Expr} so a tool reads either the same way.
 */
final class Expr
{
    use ExpressionTree;
    use Positioned;

    /**
     * @param  array<string, mixed>  $props
     */
    public function __construct(
        public readonly ExprKind $kind,
        public readonly array $props = [],
    ) {}

    public function isCall(): bool
    {
        return $this->kind === ExprKind::Call;
    }
}
