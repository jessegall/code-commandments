<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Spatie\SpatieDataNode;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Scribes\Backend\PreferOptionalCreateScribe;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\PreferOptionalCreate;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Flags a raw `new Optional` in a runtime expression and prefers Spatie's built-in `Optional::create()`
 * factory. A parameter/property DEFAULT (where a static call is illegal, so `new` is required) and an
 * attribute argument are spared. Auto-fixable: rewrite to `Optional::create()`.
 */
final class PreferOptionalCreateDetector implements Detector, Repentable
{
    public function sin(): Sin
    {
        return new PreferOptionalCreate();
    }

    public function scribe(): string
    {
        return PreferOptionalCreateScribe::class;
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereNew()
            ->where(static fn (SpatieDataNode $node): bool => $node->isReplaceableNewOptional())
            ->get();
    }
}
