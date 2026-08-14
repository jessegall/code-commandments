<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use JesseGall\CodeCommandments\Ast\AstNode;
use PhpParser\Node;
use PhpParser\Node\Expr\Cast;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;
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
     * Does this expression READ something out of a value — a property, a method, a call over it — rather
     * than COMPUTE a new one from it?
     *
     * A projection is a piece of the subject, which is why handing the subject over lets the callee take
     * that piece itself. Arithmetic is not: `-$maxDelta` is a second, different number, and nothing was
     * read out of `$maxDelta` (#494). A cast is transparent — `(string) $row->externalId` is still a read.
     */
    public static function isReadOfSomething(Node $expr): bool
    {
        while ($expr instanceof Cast) {
            $expr = $expr->expr;
        }

        return $expr instanceof PropertyFetch
            || $expr instanceof NullsafePropertyFetch
            || $expr instanceof MethodCall
            || $expr instanceof NullsafeMethodCall
            || $expr instanceof StaticCall
            || $expr instanceof FuncCall;
    }

    /**
     * Does this expression pass through a `self::`/`static::` call — a step only the CALLER can take,
     * since `self` names a different class on the other side of a call and the helper is usually private
     * to this one (#492)?
     */
    public static function routesThroughSelf(Node $expr): bool
    {
        foreach (new NodeFinder()->findInstanceOf([$expr], StaticCall::class) as $call) {
            if ($call->class instanceof Name && in_array(strtolower($call->class->toString()), ['self', 'static'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is this `X::class` — a class NAMED as a value, the one class constant a callee could be handed and
     * work from? `Html::CONTAINER` is a constant like any literal: two parameters given the same one
     * share a spelling, not a subject.
     */
    public static function namesAClass(Node $node): bool
    {
        return $node instanceof ClassConstFetch
            && $node->name instanceof Identifier
            && strtolower($node->name->toString()) === 'class';
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
