<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Detector;

/**
 * A Spatie Data class whose every promoted field is optional — nullable or
 * defaulted. The type then tells no truth about what's actually required, so
 * every consumer must re-validate what should have been guaranteed at `::from()`.
 * Make the required fields non-nullable; let `from()` fail hard on a real miss.
 * Points at spatie-data.
 */
final class AllNullableDataDetector implements Detector
{
    private const string DATA = 'Spatie\\LaravelData\\Data';

    public function skill(): string
    {
        return 'spatie-data';
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereClassExtending(self::DATA)
            ->where(static fn (AstNode $node): bool => $node->everyConstructorParamOptional())
            ->get();
    }
}
