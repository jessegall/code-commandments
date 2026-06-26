<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Detector;

/**
 * Constructing a Spatie `Data` object with `new` instead of `::from()` — the
 * raw `new` skips name mapping, casts, and validation. Points at spatie-data.
 */
final class NewDataObjectDetector implements Detector
{
    private const string DATA = 'Spatie\\LaravelData\\Data';

    public function skill(): string
    {
        return 'spatie-data';
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereNew()
            ->reject(static fn (AstNode $node): bool => $node->newClassName() === null)
            ->where(static fn (AstNode $node): bool => $codebase->extends($node->newClassName(), self::DATA))
            ->get();
    }
}
