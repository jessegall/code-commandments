<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\RawDecodedArrayReturn;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;

/**
 * Detects raw `json_decode(...)` arrays returned from boundaries. Wrap in value objects at the boundary;
 * DTO factory calls (Data::from(json_decode(...))) are exempt, as is a ROUND-TRIP of our own value
 * (`json_decode(json_encode($x))`), where nothing crosses a boundary. Points at value-objects.
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
            ->reject(static fn (AstNode $node): bool => $node->decodesItsOwnEncoding())
            ->get();
    }
}
