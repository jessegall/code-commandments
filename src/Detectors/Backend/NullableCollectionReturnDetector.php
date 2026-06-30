<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Detector;

/**
 * A method declared to return `?array` / `array | null` — a collection modelled
 * as "the list, or null", forcing every caller to guard before iterating.
 * "Nothing" has a natural empty form: return `[]`. Points at absence.
 */
final class NullableCollectionReturnDetector implements Detector
{
    public function skill(): string
    {
        return 'backend/absence';
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->where(static fn (AstNode $node): bool => $node->returnsNullableArray())
            ->get();
    }
}
