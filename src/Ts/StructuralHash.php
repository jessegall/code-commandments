<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ts;

use JesseGall\CodeCommandments\Support\ClassName;
use JesseGall\CodeCommandments\Ts\Expr\Expr;
use JesseGall\CodeCommandments\Ts\Expr\ExprKind;
use JesseGall\CodeCommandments\Ts\Node\Node;
use JesseGall\CodeCommandments\Ts\Node\TypeNode;

/**
 * A formatting-blind fingerprint of a TypeScript subtree, read through the walk hooks every node
 * already answers — its kind, the names it declares, its expressions and its children — never its
 * source text, so spacing, comments and quote style do not count. Type annotations are left out,
 * as the expression parser erases an `as` cast: what runs is the same with or without them.
 * {@see normalized} also blanks local names and string/number literals for type-2 clone detection,
 * keeping what is called and which members are read: the backend's
 * {@see \JesseGall\CodeCommandments\Ast\Support\StructuralHash} drawn over the other language.
 */
final class StructuralHash
{
    public static function of(Node $node): string
    {
        return sha1(self::node($node, false));
    }

    public static function normalized(Node $node): string
    {
        return sha1(self::node($node, true));
    }

    /**
     * How many nodes and expressions the subtree holds — the size a clone rule floors trivial bodies by.
     */
    public static function weight(Node $node): int
    {
        $weight = 1;

        foreach ($node->expressions() as $expression) {
            $weight += count($expression->flatten());
        }

        foreach ($node->nested() as $child) {
            $weight += self::weight($child);
        }

        return $weight;
    }

    private static function node(Node $node, bool $normalize): string
    {
        if ($node instanceof TypeNode) {
            return 'T:' . $node->render();
        }

        $parts = [ClassName::short($node::class)];

        if (! $normalize) {
            $parts[] = implode(',', $node->declaredNames());
        }

        if (self::isLeaf($node) && ! ($normalize && $node->declaredNames() !== [])) {
            $parts[] = $node->render();
        }

        foreach ($node->expressions() as $expression) {
            $parts[] = self::expression($expression, $normalize);
        }

        foreach ($node->children() as $child) {
            $parts[] = self::node($child, $normalize);
        }

        return '(' . implode('|', $parts) . ')';
    }

    /**
     * A node that holds nothing a walk reaches — no children, no expressions — is ALL its rendering:
     * `break;` and `continue;` differ only there. A rendering is built from the node, never its
     * source, so it is as formatting-blind as the rest; one that names something is left out when
     * normalising, since names are what normalising blanks.
     */
    private static function isLeaf(Node $node): bool
    {
        return $node->children() === [] && $node->expressions() === [];
    }

    private static function expression(Expr $expression, bool $normalize): string
    {
        if ($normalize && $expression->is(ExprKind::Identifier)) {
            return 'id';
        }

        if ($expression->is(ExprKind::Literal)) {
            return self::literal($expression, $normalize);
        }

        $parts = [$expression->kind->value];

        foreach ($expression->props as $key => $value) {
            $parts[] = $key . '=' . self::value($value, $normalize, $expression->isCall() && $key === 'callee');
        }

        return '(' . implode(',', $parts) . ')';
    }

    /**
     * A literal by its type and VALUE — never its spelling, so `'a'` and `"a"` are one string. Normalising
     * blanks the value of a string or a number; `true`, `null` and the rest are not data but meaning.
     */
    private static function literal(Expr $literal, bool $normalize): string
    {
        $blanked = $normalize && in_array($literal->literalType(), ['string', 'number'], true);

        return 'lit:' . $literal->literalType() . ':' . ($blanked ? '_' : self::value($literal->get('value'), false));
    }

    /**
     * $callee: the value is what a call calls — its name survives normalising, since two bodies that
     * call different functions do different things.
     */
    private static function value(mixed $value, bool $normalize, bool $callee = false): string
    {
        return match (true) {
            $value instanceof Expr => self::expression($value, $normalize && ! ($callee && $value->is(ExprKind::Identifier))),
            $value instanceof Node => self::node($value, $normalize),
            is_array($value) => '[' . implode(',', array_map(static fn (mixed $item) => self::value($item, $normalize), $value)) . ']',
            is_bool($value) => $value ? '1' : '0',
            is_scalar($value) => (string) $value,
            default => 'null',
        };
    }
}
