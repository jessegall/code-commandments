<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\RawDecodedArrayReturn;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Detector;

/**
 * Returning a freshly-decoded payload straight out of a boundary — the raw
 * `array` from `json_decode(...)` crossing back into the app untyped. The
 * boundary is exactly where the shape is known; wrap it in a value object
 * (`TrackingStatus::from(json_decode(...))`) so the rest of the code never
 * touches a loose array. Points at value-objects.
 *
 * A decode handed straight to a DTO factory (`return Data::from(json_decode(...))`)
 * is the cure, not the smell — there the decode is an argument, not the return.
 */
final class RawDecodedArrayReturnDetector implements Detector
{
    public function sin(): Sin
    {
        return new RawDecodedArrayReturn();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereFunction('json_decode')
            ->where(static fn (AstNode $node): bool => $node->isReturnedValue())
            ->get();
    }
}
