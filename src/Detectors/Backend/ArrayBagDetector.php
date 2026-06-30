<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Support\EloquentCast;
use JesseGall\CodeCommandments\Detectors\Detector;

/**
 * An `array` parameter read by a string-literal key (`$bag['total']`) — a
 * structured bag that should be a typed value object. Points at value-objects.
 *
 * Dynamic-key (`$m[$key]`) and positional (`$cols[0]`) access is left alone:
 * those are a genuine map or tuple, not a named-field shape.
 *
 * An Eloquent CAST is exempt: the framework dictates its `$attributes` array
 * parameter (`get`/`set($model, $key, $value, $attributes)`) and passes the raw row
 * — reading it by key (`$attributes['type']`) is the only option, not a bag the
 * author chose.
 */
final class ArrayBagDetector implements Detector
{
    public function skill(): string
    {
        return 'backend/value-objects';
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->where(static fn (AstNode $node): bool => $node->arrayKeyIsString())
            ->where(static fn (AstNode $node): bool => $node->enclosingParamIsArray($node->arrayBaseName() ?? ''))
            ->reject(static fn (AstNode $node): bool => EloquentCast::is($codebase, $node->enclosingClassName()))
            ->get();
    }
}
