<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

use JesseGall\CodeCommandments\Vue\Expr\Expr;

/**
 * One edge of the {@see ComponentGraph}: a place a parent renders a child, and the
 * expression bound to each of the child's props at that site — `<Child :total="order.sum"/>`
 * → `['total' => <order.sum>]`. The raw material of top-down prop typing: the child's `total`
 * is whatever `order.sum` is in THIS parent's scope.
 */
final class ComponentUsage
{
    /**
     * @param  array<string, Expr>  $bindings  the child's prop => the expression bound to it
     */
    public function __construct(
        public readonly Sfc $parent,
        public readonly array $bindings,
    ) {}
}
