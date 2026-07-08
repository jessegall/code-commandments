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
 * A formatting-blind fingerprint of an AST subtree by structure alone — node types and
 * values, never attributes. Two functions differing only in spacing/comments hash the same.
 * {@see normalized} also blanks variable names and values for type-2 clone detection.
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

    /**
     * A fingerprint that INLINES local aliases — a variable named in $aliases serializes AS the expression it
     * was assigned — so `$objSome` (from `$objSome = $obj->some`) hashes exactly like `$obj->some`. Two guards
     * that check the same thing, one via locals and one inline, fingerprint the same. Cycle-guarded.
     *
     * @param  array<string, Node>  $aliases  local variable name → the expression assigned to it
     */
    public static function canonical(Node $node, array $aliases): string
    {
        return sha1(self::serialize($node, false, $aliases));
    }

    /**
     * @param  array<string, Node>  $aliases
     * @param  array<string, true>  $inlining  aliases currently being expanded (cycle guard)
     */
    private static function serialize(Node $node, bool $normalize, array $aliases = [], array $inlining = []): string
    {
        if ($aliases !== [] && $node instanceof Variable && is_string($node->name)
            && isset($aliases[$node->name]) && ! isset($inlining[$node->name])) {
            return self::serialize($aliases[$node->name], $normalize, $aliases, $inlining + [$node->name => true]);
        }

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

            $parts[] = $name . ':' . self::value($node->$name, $normalize, $aliases, $inlining);
        }

        return '(' . implode(',', $parts) . ')';
    }

    /**
     * @param  array<string, Node>  $aliases
     * @param  array<string, true>  $inlining
     */
    private static function value(mixed $value, bool $normalize, array $aliases = [], array $inlining = []): string
    {
        if ($value instanceof Node) {
            return self::serialize($value, $normalize, $aliases, $inlining);
        }

        if (is_array($value)) {
            return '[' . implode(',', array_map(static fn (mixed $item): string => self::value($item, $normalize, $aliases, $inlining), $value)) . ']';
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
