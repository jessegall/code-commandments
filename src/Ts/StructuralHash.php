<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ts;

use JesseGall\CodeCommandments\SyntaxExpression;
use JesseGall\CodeCommandments\SyntaxHash;
use JesseGall\CodeCommandments\SyntaxNode;
use JesseGall\CodeCommandments\Ts\Expr\ExprKind;
use JesseGall\CodeCommandments\Ts\Node\Node;
use JesseGall\CodeCommandments\Ts\Node\TypeNode;

/**
 * The {@see SyntaxHash} of a TypeScript subtree. Type annotations read as one leaf, as the expression
 * parser erases an `as` cast: what runs is the same with or without them. The backend's
 * {@see \JesseGall\CodeCommandments\Ast\Support\StructuralHash} drawn over the other language.
 */
final class StructuralHash extends SyntaxHash
{
    protected static function isName(SyntaxExpression $expression): bool
    {
        return $expression->is(ExprKind::Identifier);
    }

    /**
     * `'a'` and `"a"` are one string; `true`, `null` and the rest are not data but meaning.
     */
    protected static function literal(SyntaxExpression $literal, bool $normalize): ?string
    {
        if (! $literal->is(ExprKind::Literal)) {
            return null;
        }

        $type = $literal->literalType();

        return 'lit:' . $type?->value . ':' . ($normalize && $type?->isData() ? '_' : (string) $literal->get('value'));
    }

    /**
     * A node's children, and the statement blocks of the arrows its own expressions hold.
     */
    protected static function nested(SyntaxNode $node): array
    {
        return $node instanceof Node ? $node->nested() : [];
    }

    protected static function leaf(SyntaxNode $node): ?string
    {
        return $node instanceof TypeNode ? 'T:' . $node->render() : null;
    }
}
