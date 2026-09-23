<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\Expr\ExprKind;
use JesseGall\CodeCommandments\Py\Expr\LiteralType;
use JesseGall\CodeCommandments\Py\ExprMatch;
use JesseGall\CodeCommandments\Py\Node\ClassDef;
use JesseGall\CodeCommandments\Py\Node\FunctionDef;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\NullableRegistryLookup;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A store that returns `None` on a miss instead of raising — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\NullableRegistryLookupDetector}: `.get(key)` on one of
 * the object's own dict attributes, returned as it is. A lookup into a map the caller handed in is the
 * caller's business, and a method answering an inherited declaration keeps the contract it was given.
 */
final class NullableRegistryLookupDetector implements Detector
{
    public function sin(): Sin
    {
        return new NullableRegistryLookup();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereCall()
            ->where(static fn (ExprMatch $call) => $call->isReturnedValue())
            ->where(static fn (ExprMatch $call) => self::missesToNone($call->expr))
            ->where(static fn (ExprMatch $call) => self::looksUpOwnDict($call))
            ->reject(static fn (ExprMatch $call): bool => $call->module->functionOf($call->expr)->isSomeAnd(
                static fn (FunctionDef $method): bool => $codebase->index()->isOverride($method, $call->module),
            ))
            ->get();
    }

    /**
     * Is $call a `.get(key)` that answers a miss with `None` — no default, or `None` given as one?
     */
    private static function missesToNone(Expr $call): bool
    {
        $arguments = $call->get('arguments');

        return $call->get('callee')->is(ExprKind::Attribute)
            && $call->get('callee')->get('name') === 'get'
            && (count($arguments) === 1 || (count($arguments) === 2 && $arguments[1]->literalType() === LiteralType::None));
    }

    /**
     * Is the dict looked into one of the object's own attributes, annotated as a dict?
     */
    private static function looksUpOwnDict(ExprMatch $call): bool
    {
        $store = $call->expr->get('callee')->get('object')->selfAttribute();

        return $store !== '' && $call->module->classOf($call->expr)->isSomeAnd(
            static fn (ClassDef $class): bool => $class->attributeAnnotation($store)->isSomeAnd(static fn (Expr $type): bool => $type->isDictType()),
        );
    }
}
