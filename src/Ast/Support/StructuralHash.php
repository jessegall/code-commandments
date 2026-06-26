<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use PhpParser\Node;

/**
 * A formatting-blind fingerprint of an AST subtree. It serialises a node by its
 * structure — node types and their sub-node values (names, operators, literals) —
 * and never reads attributes (line numbers, comments, whitespace live there). So
 * two functions that differ only in spacing, newlines, or comments hash the same;
 * a real difference in code changes the hash.
 */
final class StructuralHash
{
    public static function of(Node $node): string
    {
        return sha1(self::serialize($node));
    }

    private static function serialize(Node $node): string
    {
        $parts = [self::shortName($node::class)];

        foreach ($node->getSubNodeNames() as $name) {
            $parts[] = $name . ':' . self::value($node->$name);
        }

        return '(' . implode(',', $parts) . ')';
    }

    private static function value(mixed $value): string
    {
        if ($value instanceof Node) {
            return self::serialize($value);
        }

        if (is_array($value)) {
            return '[' . implode(',', array_map(self::value(...), $value)) . ']';
        }

        return match (true) {
            is_string($value) => 's:' . $value,
            is_bool($value) => 'b:' . ($value ? '1' : '0'),
            is_int($value), is_float($value) => 'n:' . $value,
            default => 'null',
        };
    }

    private static function shortName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts);
    }
}
