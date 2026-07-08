<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Spatie\SpatieDataNode;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Scribes\Backend\NestedTypeMissingTypeScriptScribe;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\NestedTypeMissingTypeScript;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Flags a property on a `#[TypeScript]` Data whose nested Data/backed-enum type itself lacks `#[TypeScript]`
 * — the transformer emits it as `undefined`, a silent hole in the generated frontend contract. Auto-fixable:
 * stamp `#[TypeScript]` on the nested class.
 */
final class NestedTypeMissingTypeScriptDetector implements Detector, Repentable
{
    public function sin(): Sin
    {
        return new NestedTypeMissingTypeScript();
    }

    public function scribe(): string
    {
        return NestedTypeMissingTypeScriptScribe::class;
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereField()
            ->where(static fn (SpatieDataNode $node): bool => $node->nestedWireTypeMissingTypeScript())
            ->get();
    }
}
