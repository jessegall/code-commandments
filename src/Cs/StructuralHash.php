<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cs;

use JesseGall\CodeCommandments\SyntaxExpression;
use JesseGall\CodeCommandments\SyntaxHash;
use JesseGall\CodeCommandments\SyntaxNode;

/**
 * The {@see SyntaxHash} of a C# subtree. A local name is an identifier outside a type position — the
 * type a body creates or casts to is what it does, so it survives normalising. A string, number or
 * character is data and blanks; `true`, `null` and `default` carry meaning and stay. Attributes decorate
 * code without running, so they never count.
 */
final class StructuralHash extends SyntaxHash
{
    /**
     * The literal kinds that hold data rather than meaning.
     */
    private const array DATA = ['StringLiteralExpression', 'Utf8StringLiteralExpression', 'NumericLiteralExpression', 'CharacterLiteralExpression', 'InterpolatedStringText'];

    protected static function isName(SyntaxExpression $expression): bool
    {
        return $expression instanceof Node && $expression->is('IdentifierName') && $expression->role !== 'type';
    }

    protected static function literal(SyntaxExpression $literal, bool $normalize): ?string
    {
        if (! $literal instanceof Node || ! ($literal->isConstant() || $literal->is('InterpolatedStringText'))) {
            return null;
        }

        $data = $literal->is(...self::DATA);

        return 'lit:' . $literal->kind . ':' . ($normalize && $data ? '_' : (string) $literal->text);
    }

    public static function counted(SyntaxNode $node): array
    {
        return array_values(array_filter($node->children(), static fn (SyntaxNode $child): bool => ! ($child instanceof Node && $child->is('AttributeList'))));
    }
}
