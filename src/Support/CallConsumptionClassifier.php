<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * Classifies how a single call expression's nullable/Option result is consumed,
 * given the call node and its parent. The atom the {@see CallConsumptionCensus}
 * runs over every call site of a method.
 *
 *  - DENULL      the absence is rejected: `?? throw`, a blind `->`/property deref
 *                (nullable), or `->getOrThrow()`/`->unwrap()` (Option).
 *  - HANDLES     the absence is tolerated: `?->`, `?? <realDefault>`, a `?? null`,
 *                `->getOr(...)`/`->map(...)`/…, or carried into a sink (an arg).
 *  - PASSTHROUGH the enclosing method just `return`s the call result — forward
 *                the question to THAT method's callers.
 *  - UNKNOWN     anything else (assigned to a local, a complex position): the
 *                census treats it as "can't prove de-null".
 *
 * Generalised from PreferTotalOverNullableProphet's in-class de-null check.
 */
final class CallConsumptionClassifier
{
    public const DENULL = 'denull';

    public const HANDLES = 'handles';

    public const PASSTHROUGH = 'passthrough';

    public const UNKNOWN = 'unknown';

    /** Option de-null accessors — calling one treats absence as impossible. */
    private const UNWRAP_METHODS = ['getorthrow', 'getorfail', 'unwrap'];

    /**
     * @param  'nullable'|'option'  $kind
     * @return self::DENULL|self::HANDLES|self::PASSTHROUGH|self::UNKNOWN
     */
    public function classify(Expr $call, ?Node $parent, string $kind): string
    {
        if ($parent === null) {
            return self::UNKNOWN;
        }

        return $kind === 'option'
            ? $this->classifyOption($call, $parent)
            : $this->classifyNullable($call, $parent);
    }

    private function classifyNullable(Expr $call, Node $parent): string
    {
        if ($parent instanceof Expr\BinaryOp\Coalesce && $parent->left === $call) {
            return $parent->right instanceof Expr\Throw_ ? self::DENULL : self::HANDLES;
        }

        // Plain (non-nullsafe) deref assumes the value is there.
        if ($parent instanceof Expr\MethodCall && $parent->var === $call) {
            return self::DENULL;
        }

        if ($parent instanceof Expr\PropertyFetch && $parent->var === $call) {
            return self::DENULL;
        }

        // Nullsafe chains tolerate the absence.
        if ($parent instanceof Expr\NullsafeMethodCall && $parent->var === $call) {
            return self::HANDLES;
        }

        if ($parent instanceof Expr\NullsafePropertyFetch && $parent->var === $call) {
            return self::HANDLES;
        }

        if ($parent instanceof Node\Stmt\Return_ && $parent->expr === $call) {
            return self::PASSTHROUGH;
        }

        if ($parent instanceof Node\Arg && $parent->value === $call) {
            return self::HANDLES;
        }

        return self::UNKNOWN;
    }

    private function classifyOption(Expr $call, Node $parent): string
    {
        if ($parent instanceof Expr\MethodCall
            && $parent->var === $call
            && $parent->name instanceof Node\Identifier
        ) {
            return in_array(strtolower($parent->name->toString()), self::UNWRAP_METHODS, true)
                ? self::DENULL
                : self::HANDLES;
        }

        if ($parent instanceof Node\Stmt\Return_ && $parent->expr === $call) {
            return self::PASSTHROUGH;
        }

        if ($parent instanceof Node\Arg && $parent->value === $call) {
            return self::HANDLES;
        }

        return self::UNKNOWN;
    }
}
