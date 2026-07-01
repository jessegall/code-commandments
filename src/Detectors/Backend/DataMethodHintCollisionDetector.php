<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\DataMethodHintCollision;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;

/**
 * A Spatie `Data` class with a `@method` docblock tag that names a method the class
 * ACTUALLY declares — e.g. `@method static static fromCredential(...)` over a real
 * `fromCredential()`. The IDE reports "Method with same name already defined in this
 * class", because `@method` is for the *invisible* magic overloads only: `::from()`
 * dispatches to `fromX()` factories and `::collect()` builds collections, neither
 * visible from the constructor. The tag must describe `from`/`collect`, never a
 * concrete factory's own name. Points at spatie-data.
 */
final class DataMethodHintCollisionDetector implements Detector
{
    private const string DATA = 'Spatie\\LaravelData\\Data';

    public function sin(): Sin
    {
        return new DataMethodHintCollision();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereClassExtending(self::DATA)
            ->where(static fn (AstNode $node): bool => $node->docblockMethodTagRedeclaresRealMethod())
            ->get();
    }
}
