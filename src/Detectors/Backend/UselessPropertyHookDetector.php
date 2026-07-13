<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Spatie\SpatieDataNode;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\UselessPropertyHook;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A `get` hook whose body references no `$this` (and no `parent::`) — it computes nothing
 * from the object, so the property is stored, not derived; the hook syntax is a lie usually
 * copied from an interface's `{ get; }` (which a plain property satisfies). Abstract hook
 * declarations, get/set pairs, and Data-class territory (a Spatie `Data` class or a trait
 * a Data class consumes — computed/page-object fields are their idiom) are left alone.
 * Points at type-honesty.
 */
final class UselessPropertyHookDetector implements Detector
{
    public function sin(): Sin
    {
        return new UselessPropertyHook();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereGetterHook()
            ->reject(static fn (AstNode $node): bool => $node->isAbstractHook())
            ->reject(static fn (AstNode $node): bool => $node->hookedPropertyHasSetter())
            ->reject(static fn (AstNode $node): bool => $node->referencesThis())
            ->reject(static fn (SpatieDataNode $node): bool => $node->inDataScope())
            ->get();
    }
}
