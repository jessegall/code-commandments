<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Spatie\SpatieDataNode;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\DataMethodHintCollision;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A Spatie `Data` class with a `@method` docblock tag that re-declares a method the class
 * ACTUALLY has, colliding with it (`@method static static fromCredential(...)` over a real
 * `fromCredential()`). The IDE reports "Method with same name already defined", because
 * `@method` is for the *invisible* magic overloads only: `::from()` dispatches to
 * `fromX()` factories and `::collect()` builds collections. The tag must describe
 * `from`/`collect`, never a concrete factory's own name. Points at spatie-data.
 */
final class DataMethodHintCollisionDetector implements Detector
{
    public function sin(): Sin
    {
        return new DataMethodHintCollision();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereClass()
            ->where(static fn (SpatieDataNode $node): bool => $node->isDataClass())
            ->where(static fn (AstNode $node): bool => $node->docblockMethodTagRedeclaresRealMethod())
            ->get();
    }
}
