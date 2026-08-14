<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use JesseGall\CodeCommandments\Ast\AstNode;

use JesseGall\CodeCommandments\Support\ClassName;

use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
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
     * The fingerprint of a DERIVATION with the value it is applied to blanked — every leaf (a variable, a
     * scalar, a `X::class`) reads the same, while the calls and property hops around them keep their
     * names. `Inspector::for(ChatTurn::class)->kebabName()` and the same over `DebugLine` share a shape;
     * `->snakeName()` does not. What tells "one projection written at many call sites" from a coincidence.
     *
     * Distinct from {@see normalized}, which keeps class references (a clone hunt must not fuse two
     * functions that differ only in the type they name).
     */
    public static function shape(Node $node): string
    {
        return sha1(self::serialize($node, false, [], [], true));
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
     * @param  bool                 $blankLeaves  every operand reads the same — see {@see shape}
     */
    private static function serialize(Node $node, bool $normalize, array $aliases = [], array $inlining = [], bool $blankLeaves = false): string
    {
        if ($aliases !== [] && AstNode::variableNameOf($node) !== null
            && isset($aliases[$node->name]) && ! isset($inlining[$node->name])) {
            return self::serialize($aliases[$node->name], $normalize, $aliases, $inlining + [$node->name => true]);
        }

        if ($blankLeaves && Derivation::isOperand($node)) {
            return 'Leaf';
        }

        if ($normalize) {
            $blanked = match (true) {
                AstNode::variableNameOf($node) !== null => 'Variable(name:s:$)',
                $node instanceof String_ => 'String_(value:s:_)',
                $node instanceof Int_, $node instanceof Float_ => 'Num(value:n:_)',
                default => null,
            };

            if ($blanked !== null) {
                return $blanked;
            }
        }

        $parts = [ClassName::short($node::class)];

        foreach ($node->getSubNodeNames() as $name) {
            // Redundant methods wear different names (`resolveWritable` vs
            // `resolveReadonly`), so a shape hash blanks the declaration's own name.
            if ($normalize && $name === 'name' && $node instanceof FunctionLike) {
                $parts[] = 'name:s:_';

                continue;
            }

            $parts[] = $name . ':' . self::value($node->$name, $normalize, $aliases, $inlining, $blankLeaves);
        }

        return '(' . implode(',', $parts) . ')';
    }

    /**
     * @param  array<string, Node>  $aliases
     * @param  array<string, true>  $inlining
     */
    private static function value(mixed $value, bool $normalize, array $aliases = [], array $inlining = [], bool $blankLeaves = false): string
    {
        if ($value instanceof Node) {
            return self::serialize($value, $normalize, $aliases, $inlining, $blankLeaves);
        }

        if (is_array($value)) {
            return '[' . implode(',', array_map(static fn (mixed $item) => self::value($item, $normalize, $aliases, $inlining, $blankLeaves), $value)) . ']';
        }

        return match (true) {
            is_string($value) => 's:' . $value,
            is_bool($value) => 'b:' . ($value ? '1' : '0'),
            is_int($value), is_float($value) => 'n:' . $value,
            default => 'null',
        };
    }
}
