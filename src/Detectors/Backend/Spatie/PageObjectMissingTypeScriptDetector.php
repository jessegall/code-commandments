<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Spatie\SpatieDataNode;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Scribes\Backend\PageObjectMissingTypeScriptScribe;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\PageObjectMissingTypeScript;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Flags a page object — a `Data` that composes multiple nested `Data` AND travels back in a response —
 * carrying no `#[TypeScript]`. The `.vue` page then reads it as untyped `any`, so the page/prop contract
 * goes unchecked. Auto-fixable: stamp the attribute.
 */
final class PageObjectMissingTypeScriptDetector implements Detector, Repentable
{
    public function sin(): Sin
    {
        return new PageObjectMissingTypeScript();
    }

    public function scribe(): string
    {
        return PageObjectMissingTypeScriptScribe::class;
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereClass()
            ->where(static fn (SpatieDataNode $node): bool => $node->pageObjectMissingTypeScript())
            ->get();
    }
}
