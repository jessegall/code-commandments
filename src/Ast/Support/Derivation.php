<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use JesseGall\CodeCommandments\Ast\AstNode;
use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;

/**
 * What an expression is APPLIED TO, as against the steps it applies. The one home of the operand/step
 * split, so the shape fingerprint ({@see StructuralHash::shape}) and the rules that count what a
 * derivation depends on agree on where a projection stops and its subject begins.
 */
final class Derivation
{
    /**
     * Is this node an OPERAND — a variable, a literal, or a class reference (`Order::class`, an enum
     * case)? The values a derivation consumes; everything else is a call or a hop that transforms them.
     */
    public static function isOperand(Node $node): bool
    {
        return AstNode::variableNameOf($node) !== null
            || $node instanceof String_
            || $node instanceof Int_
            || $node instanceof Float_
            || $node instanceof ClassConstFetch;
    }

    /**
     * How many operands $expr consumes. One means the expression is a pure projection of a single
     * subject, so a callee handed that subject can derive the rest itself; two or more means it also
     * depends on something only the CALLER holds, and moving it down would drag that along too.
     */
    public static function operandsIn(Node $expr): int
    {
        if (self::isOperand($expr)) {
            return 1;
        }

        $operands = 0;

        foreach ($expr->getSubNodeNames() as $name) {
            $operands += self::countIn($expr->$name);
        }

        return $operands;
    }

    /**
     * Every operand $expr consumes, in source order — the values it is applied to.
     *
     * @return list<Node>
     */
    public static function operandsOf(Node $expr): array
    {
        if (self::isOperand($expr)) {
            return [$expr];
        }

        $operands = [];

        foreach ($expr->getSubNodeNames() as $name) {
            $operands = [...$operands, ...self::flatten($expr->$name)];
        }

        return $operands;
    }

    /**
     * @return list<Node>
     */
    private static function flatten(mixed $value): array
    {
        if ($value instanceof Node) {
            return self::operandsOf($value);
        }

        if (! is_array($value)) {
            return [];
        }

        $operands = [];

        foreach ($value as $item) {
            $operands = [...$operands, ...self::flatten($item)];
        }

        return $operands;
    }

    /**
     * The single operand $expr is applied to, or null when it consumes none or several. The SUBJECT a
     * projection projects — what a callee would have to be handed to derive the rest itself.
     */
    public static function soleOperandIn(Node $expr): ?Node
    {
        if (self::isOperand($expr)) {
            return $expr;
        }

        $found = null;

        foreach ($expr->getSubNodeNames() as $name) {
            foreach (self::operandsUnder($expr->$name) as $operand) {
                if ($found !== null) {
                    return null;
                }

                $found = $operand;
            }
        }

        return $found;
    }

    /**
     * @return list<Node>
     */
    private static function operandsUnder(mixed $value): array
    {
        if ($value instanceof Node) {
            $sole = self::soleOperandIn($value);

            return $sole === null ? array_fill(0, self::operandsIn($value), $value) : [$sole];
        }

        if (! is_array($value)) {
            return [];
        }

        $operands = [];

        foreach ($value as $item) {
            $operands = [...$operands, ...self::operandsUnder($item)];
        }

        return $operands;
    }

    private static function countIn(mixed $value): int
    {
        if ($value instanceof Node) {
            return self::operandsIn($value);
        }

        return is_array($value) ? array_sum(array_map(self::countIn(...), $value)) : 0;
    }
}
