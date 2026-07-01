<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Laravel;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Laravel\LaravelNode;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\RequestAccessorRecast;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A typed request accessor immediately re-flattened to a bare string — `$request->string($k)->toString()`
 * or `(string) $request->string($k)`. The half-fix reaches for the typed getter and then throws the
 * type away at the call site; read it through a named getter instead. Points at laravel-idioms.
 */
final class RequestAccessorRecastDetector implements Detector
{
    public function sin(): Sin
    {
        return new RequestAccessorRecast();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereMethod('string')
            ->isUsedOn(...LaravelNode::REQUEST_TYPES)
            ->where(static fn (AstNode $node): bool => $node->isReCoercedToString())
            ->get();
    }
}
