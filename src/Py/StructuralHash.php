<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py;

use JesseGall\CodeCommandments\Py\Expr\ExprKind;
use JesseGall\CodeCommandments\Py\Node\ExprStmt;
use JesseGall\CodeCommandments\SyntaxExpression;
use JesseGall\CodeCommandments\SyntaxHash;
use JesseGall\CodeCommandments\SyntaxNode;

/**
 * The {@see SyntaxHash} of a Python subtree. A string standing alone as a statement — a docstring —
 * runs nothing, so it never counts; an f-string's text parts are string literals, blanked like any other.
 */
final class StructuralHash extends SyntaxHash
{
    protected static function isName(SyntaxExpression $expression): bool
    {
        return $expression->is(ExprKind::Name);
    }

    protected static function literal(SyntaxExpression $literal, bool $normalize): ?string
    {
        $type = $literal->literalType();

        if ($type === null) {
            return null;
        }

        return 'lit:' . $type->value . ':' . ($normalize && $type->isData() ? '_' : (string) $literal->get('value'));
    }

    protected static function children(SyntaxNode $node): array
    {
        return array_values(array_filter(
            $node->children(),
            static fn (SyntaxNode $child): bool => ! ($child instanceof ExprStmt && $child->isBareString()),
        ));
    }
}
