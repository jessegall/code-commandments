<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Expr;

use RuntimeException;

/**
 * A property asked of an expression that its KIND does not carry. Which properties a kind holds is
 * fixed by the kind — a `Binary` has `left`, `right` and `op` — so this is a programming error, not
 * an absence: read as "not set" it would silently take a branch nobody meant.
 */
final class UnknownProperty extends RuntimeException
{
    /**
     * @param  list<string>  $available
     */
    public static function of(ExprKind $kind, string $key, array $available): self
    {
        $has = $available === [] ? 'nothing' : implode(', ', $available);

        return new self("A {$kind->name} expression has no `{$key}` — it carries {$has}.");
    }
}
