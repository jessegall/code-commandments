<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use JesseGall\CodeCommandments\Span;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\BinaryOp\Equal;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\BinaryOp\NotEqual;
use PhpParser\Node\Expr\BinaryOp\NotIdentical;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Empty_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Isset_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar;

/**
 * A CONDITION, negated — the single home of "flip this test" for every rewrite that inverts one
 * (a `continue` guard, an early return), so no scribe re-decides how a `!` reads. The text itself is
 * never rewritten: `!` simply goes in front, wrapped in parentheses ONLY when the condition needs them
 * to keep its meaning — but a call, a variable, a property read or an `isset` is already one unit, and
 * wrapping THAT is noise a human then has to undo (#416). Negating a negation is the condition itself,
 * and an EQUALITY test is flipped at the operator ({@see INVERSE}) rather than prefixed, because
 * `$a !== $b` is what a human writes where `! ($a === $b)` is what a machine emits.
 */
final class Negation
{
    /**
     * Every expression that already reads as ONE unit, so a leading `!` cannot swallow part of it.
     * Anything absent (a comparison, a logical operator, a ternary, `instanceof`, a coalesce, an
     * assignment) takes the parentheses — the safe default, never a De Morgan rewrite.
     *
     * @var list<class-string<Expr>>
     */
    private const array SELF_CONTAINED = [
        Variable::class,
        PropertyFetch::class,
        NullsafePropertyFetch::class,
        StaticPropertyFetch::class,
        ArrayDimFetch::class,
        FuncCall::class,
        MethodCall::class,
        NullsafeMethodCall::class,
        StaticCall::class,
        ConstFetch::class,
        ClassConstFetch::class,
        Isset_::class,
        Empty_::class,
        Scalar::class,
    ];

    /**
     * The EQUALITY operators, each mapped to its exact inverse — the one flip that needs no `!` at
     * all. Deliberately only these four: they are true inverses for every PHP value (`NAN !== NAN`
     * is exactly `! (NAN === NAN)`), while `<` and `>=` are NOT — both are false for a NAN operand,
     * so turning one into the other would change what the code does. A relational comparison keeps
     * the parenthesised `!`, which is always faithful.
     *
     * @var array<class-string<Expr>, string>
     */
    private const array INVERSE = [
        Identical::class => '!==',
        NotIdentical::class => '===',
        Equal::class => '!=',
        NotEqual::class => '==',
    ];

    /**
     * The source of $condition with its truth flipped, ready to drop into an `if (…)`.
     */
    public static function of(Expr $condition, string $source): string
    {
        if ($condition instanceof BooleanNot) {
            return self::sourceOf($condition->expr, $source); // `! (! $x)` asks the same question as `$x`.
        }

        $inverse = self::INVERSE[$condition::class] ?? null;

        if ($inverse !== null && $condition instanceof BinaryOp) {
            return self::sourceOf($condition->left, $source) . " {$inverse} " . self::sourceOf($condition->right, $source);
        }

        $text = self::sourceOf($condition, $source);

        return self::isSelfContained($condition) ? "! {$text}" : "! ({$text})";
    }

    private static function sourceOf(Expr $expr, string $source): string
    {
        return Span::slice($source, $expr->getStartFilePos(), $expr->getEndFilePos());
    }

    private static function isSelfContained(Expr $condition): bool
    {
        foreach (self::SELF_CONTAINED as $unit) {
            if ($condition instanceof $unit) {
                return true;
            }
        }

        return false;
    }
}
