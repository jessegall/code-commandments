<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\AllNullableData;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;

/**
 * A Spatie Data class whose every promoted field is NULLABLE. The type then tells no
 * truth about what's actually required, so every consumer must re-validate what should
 * have been guaranteed at `::from()`. Make the required fields non-nullable; let `from()`
 * fail hard on a real miss. Points at spatie-data.
 *
 * A non-nullable field with a typed default (`int $x = 0`, `string $s = ''`) is an HONEST
 * optional — a value object / accumulator with a sensible identity (e.g. a zero token
 * count), not a dodged requirement — so a class holding even one such field is not flagged.
 */
final class AllNullableDataDetector implements Detector
{
    private const string DATA = 'Spatie\\LaravelData\\Data';

    public function sin(): Sin
    {
        return new AllNullableData();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereClassExtending(self::DATA)
            ->where(static fn (AstNode $node): bool => $node->everyConstructorParamNullable())
            ->get();
    }
}
