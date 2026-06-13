<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\CallGraph;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;

/**
 * Shared classification + fingerprinting for "computed value or fallback"
 * expressions. Four surface forms collapse to the same {value, fallback} shape:
 *
 *   - `LHS ?? RHS`                         (null coalesce)
 *   - `LHS ?: RHS`                         (Elvis / short ternary)
 *   - `X === null ? RHS : X`               (full ternary null check)
 *   - `isset(X) ? X : RHS`, `empty(X) ? …` (full ternary presence/empty check)
 *
 * Both the {@see CodebaseIndex} (which counts occurrences across the scroll)
 * and the prophet pipe (which decides what to flag in one file) call THIS so
 * their notion of "the same expression" can never drift.
 */
final class FallbackFingerprint
{
    /**
     * Split a fallback expression into its computed value side and its
     * fallback side, or null when the node is not a recognised fallback form.
     *
     * @return array{left: Expr, fallback: Expr, op: string}|null
     */
    public static function parts(Node $node): ?array
    {
        if ($node instanceof Expr\BinaryOp\Coalesce) {
            return ['left' => $node->left, 'fallback' => $node->right, 'op' => '??'];
        }

        if ($node instanceof Expr\Ternary) {
            // Elvis `$a ?: $b` — a ternary with an empty middle.
            if ($node->if === null) {
                return $node->else === null
                    ? null
                    : ['left' => $node->cond, 'fallback' => $node->else, 'op' => '?:'];
            }

            return self::fullTernaryParts($node);
        }

        return null;
    }

    /**
     * Whether the node is a fallback worth naming: a recognised form whose
     * VALUE side is a COMPUTED chain (contains a static or free-function call),
     * and whose fallback produces a real value (not bare `null`).
     *
     * The "value has a static/func call" rule is the boundary against the
     * null-object prophets: a bare `$nullable?->method() ?? x` has no static
     * call, so it stays their territory.
     */
    public static function qualifies(Node $node): bool
    {
        $parts = self::parts($node);

        if ($parts === null || self::isNullLiteral($parts['fallback'])) {
            return false;
        }

        return (new NodeFinder)->findFirst(
            $parts['left'],
            static fn (Node $n): bool => $n instanceof Expr\StaticCall || $n instanceof Expr\FuncCall,
        ) !== null;
    }

    /**
     * A whitespace-normalised fingerprint of the expression's source. Two
     * occurrences match iff their source is identical bar whitespace — and the
     * operator (`??` / `?:` / the exact ternary) is part of the source, so the
     * forms never merge (collapsing them would change runtime behaviour).
     */
    public static function fingerprint(Node $node, string $content): ?string
    {
        $start = $node->getStartFilePos();
        $end = $node->getEndFilePos();

        if ($start === null || $end === null || $start < 0 || $end < $start) {
            return null;
        }

        $normalized = preg_replace('/\s+/', '', substr($content, $start, $end - $start + 1));

        return $normalized === '' || $normalized === null ? null : $normalized;
    }

    /**
     * A full ternary that is a null / isset / empty check, split into value and
     * fallback by the condition's polarity. Returns null for any other ternary.
     *
     * @return array{left: Expr, fallback: Expr, op: string}|null
     */
    private static function fullTernaryParts(Expr\Ternary $node): ?array
    {
        if ($node->if === null || $node->else === null) {
            return null;
        }

        $cond = $node->cond;
        $negated = false;

        if ($cond instanceof Expr\BooleanNot) {
            $negated = true;
            $cond = $cond->expr;
        }

        $presentWhenTrue = self::conditionPresence($cond);

        if ($presentWhenTrue === null) {
            return null;
        }

        if ($negated) {
            $presentWhenTrue = ! $presentWhenTrue;
        }

        // condition true → value present → `if` is the value, `else` the fallback
        return $presentWhenTrue
            ? ['left' => $node->if, 'fallback' => $node->else, 'op' => 'ternary']
            : ['left' => $node->else, 'fallback' => $node->if, 'op' => 'ternary'];
    }

    /**
     * Does a true condition mean "the value is present" (true), "the value is
     * absent" (false), or is this not a presence/null/empty check (null)?
     */
    private static function conditionPresence(Expr $cond): ?bool
    {
        if ($cond instanceof Expr\Isset_) {
            return true;
        }

        if ($cond instanceof Expr\Empty_) {
            return false;
        }

        if ($cond instanceof Expr\FuncCall
            && $cond->name instanceof Node\Name
            && strtolower($cond->name->toString()) === 'is_null'
        ) {
            return false;
        }

        if (($cond instanceof Expr\BinaryOp\Identical || $cond instanceof Expr\BinaryOp\Equal)
            && self::hasNullOperand($cond)
        ) {
            return false;
        }

        if (($cond instanceof Expr\BinaryOp\NotIdentical || $cond instanceof Expr\BinaryOp\NotEqual)
            && self::hasNullOperand($cond)
        ) {
            return true;
        }

        return null;
    }

    private static function hasNullOperand(Expr\BinaryOp $cond): bool
    {
        return self::isNullLiteral($cond->left) || self::isNullLiteral($cond->right);
    }

    private static function isNullLiteral(Expr $expr): bool
    {
        return $expr instanceof Expr\ConstFetch
            && strtolower($expr->name->toString()) === 'null';
    }
}
