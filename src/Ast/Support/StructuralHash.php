<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;

/**
 * A formatting-blind fingerprint of an AST subtree. It serialises a node by its
 * structure — node types and their sub-node values (names, operators, literals) —
 * and never reads attributes (line numbers, comments, whitespace live there). So
 * two functions that differ only in spacing, newlines, or comments hash the same;
 * a real difference in code changes the hash.
 *
 * {@see normalized} goes one step fuzzier: it also blanks variable names and
 * scalar literal values, so two functions with the same SHAPE that differ only in
 * their variable names or constants (a type-2 clone) hash the same.
 */
final class StructuralHash
{
    public static function of(Node $node): string
    {
        return sha1(self::serialize($node, false));
    }

    public static function normalized(Node $node): string
    {
        return sha1(self::serialize($node, true));
    }

    private static function serialize(Node $node, bool $normalize): string
    {
        if ($normalize) {
            $blanked = match (true) {
                $node instanceof Variable && is_string($node->name) => 'Variable(name:s:$)',
                $node instanceof String_ => 'String_(value:s:_)',
                $node instanceof Int_, $node instanceof Float_ => 'Num(value:n:_)',
                default => null,
            };

            if ($blanked !== null) {
                return $blanked;
            }
        }

        $parts = [self::shortName($node::class)];

        foreach ($node->getSubNodeNames() as $name) {
            // Redundant methods wear different names (`resolveWritable` vs
            // `resolveReadonly`), so a shape hash blanks the declaration's own name.
            if ($normalize && $name === 'name' && $node instanceof FunctionLike) {
                $parts[] = 'name:s:_';

                continue;
            }

            $parts[] = $name . ':' . self::value($node->$name, $normalize);
        }

        return '(' . implode(',', $parts) . ')';
    }

    private static function value(mixed $value, bool $normalize): string
    {
        if ($value instanceof Node) {
            return self::serialize($value, $normalize);
        }

        if (is_array($value)) {
            return '[' . implode(',', array_map(static fn (mixed $item): string => self::value($item, $normalize), $value)) . ']';
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
