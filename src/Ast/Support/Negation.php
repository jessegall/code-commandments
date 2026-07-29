<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use JesseGall\CodeCommandments\Scribes\Span;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrayDimFetch;
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
 * to keep its meaning. `!` binds tighter than every comparison and every `&&`/`||`, so `$a === $b`
 * does need them — but a call, a variable, a property read or an `isset` is already one unit, and
 * wrapping THAT is noise a human then has to undo (#416). Negating a negation is the condition itself.
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
     * The source of $condition with its truth flipped, ready to drop into an `if (…)`.
     */
    public static function of(Expr $condition, string $source): string
    {
        if ($condition instanceof BooleanNot) {
            return self::sourceOf($condition->expr, $source); // `! (! $x)` asks the same question as `$x`.
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
