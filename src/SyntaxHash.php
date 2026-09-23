<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

use JesseGall\CodeCommandments\Support\ClassName;

/**
 * A formatting-blind fingerprint of a subtree, read through the walk hooks every {@see SyntaxNode}
 * answers — its kind and variant, the names it declares, its expressions and its children — never its
 * source text, so spacing, comments and quote style do not count. {@see self::normalized} also blanks
 * local names and string/number literals for type-2 clone detection, keeping what is called and which
 * members are read. A language states only what differs: which expression is a local name, how a
 * literal reads, and which children count.
 */
abstract class SyntaxHash
{
    public static function of(SyntaxNode $node): string
    {
        return sha1(self::node($node, false));
    }

    public static function normalized(SyntaxNode $node): string
    {
        return sha1(self::node($node, true));
    }

    /**
     * A formatting-blind fingerprint of one expression — two spellings of the same read hash alike.
     */
    public static function ofExpression(SyntaxExpression $expression): string
    {
        return sha1(self::expression($expression, false));
    }

    /**
     * How many nodes and expressions the subtree holds — the size a clone rule floors trivial bodies by.
     */
    public static function weight(SyntaxNode $node): int
    {
        $weight = 1;

        foreach ($node->expressions() as $expression) {
            $weight += count($expression->flatten());
        }

        foreach (static::nested($node) as $child) {
            $weight += static::weight($child);
        }

        return $weight;
    }

    /**
     * Is $expression a local name — the thing normalising blanks?
     */
    abstract protected static function isName(SyntaxExpression $expression): bool;

    /**
     * $literal by its type and VALUE, never its spelling; normalising blanks the value of data (a string,
     * a number) but keeps a constant that carries meaning. Null when $literal is no literal.
     */
    abstract protected static function literal(SyntaxExpression $literal, bool $normalize): ?string;

    /**
     * The children of $node that count — all of them, unless the language has some that do nothing.
     *
     * @return list<SyntaxNode>
     */
    public static function counted(SyntaxNode $node): array
    {
        return $node->children();
    }

    /**
     * The nodes beneath $node that weigh — its children, and whatever else the language nests there.
     *
     * @return list<SyntaxNode>
     */
    protected static function nested(SyntaxNode $node): array
    {
        return static::counted($node);
    }

    /**
     * $node read whole, when its language reads it as one leaf rather than through its hooks.
     */
    protected static function leaf(SyntaxNode $node): ?string
    {
        return null;
    }

    private static function node(SyntaxNode $node, bool $normalize): string
    {
        $leaf = static::leaf($node);

        if ($leaf !== null) {
            return $leaf;
        }

        $parts = [ClassName::short($node::class)];

        if (! $normalize) {
            $parts[] = implode(',', $node->declaredNames());
        }

        $parts[] = $node->variant();

        foreach ($node->expressions() as $expression) {
            $parts[] = self::expression($expression, $normalize);
        }

        foreach (static::counted($node) as $child) {
            $parts[] = self::node($child, $normalize);
        }

        return '(' . implode('|', $parts) . ')';
    }

    private static function expression(SyntaxExpression $expression, bool $normalize): string
    {
        if ($normalize && static::isName($expression)) {
            return 'id';
        }

        $literal = static::literal($expression, $normalize);

        if ($literal !== null) {
            return $literal;
        }

        $parts = [$expression->kindName()];

        foreach ($expression->props as $key => $value) {
            $parts[] = $key . '=' . self::value($value, $normalize, $expression->isCall() && $key === 'callee');
        }

        return '(' . implode(',', $parts) . ')';
    }

    /**
     * $callee: the value is what a call calls — its name survives normalising, since two bodies that
     * call different functions do different things.
     */
    private static function value(mixed $value, bool $normalize, bool $callee = false): string
    {
        return match (true) {
            $value instanceof SyntaxExpression => self::expression($value, $normalize && ! ($callee && static::isName($value))),
            $value instanceof SyntaxNode => self::node($value, $normalize),
            is_array($value) => '[' . implode(',', array_map(static fn (mixed $item) => self::value($item, $normalize), $value)) . ']',
            is_bool($value) => $value ? '1' : '0',
            is_scalar($value) => (string) $value,
            default => 'null',
        };
    }
}
