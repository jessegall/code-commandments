<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Detector;

/**
 * Filling an argument with a manufactured fake on absence — `name: $row['name']
 * ?? ''`, `(int) ($row['id'] ?? 0)`. An empty string / zero / `[]` born at the
 * boundary looks valid and isn't; it drops the absence signal and is trusted
 * everywhere downstream. Throw, or make the slot honestly optional — decide at
 * the source. Points at fix-at-the-source.
 *
 * A real default (`?? 'EUR'`, `?? 30`) is a deliberate value, not a fake, so only
 * empty/zero/false fills are flagged.
 */
final class ManufacturedFakeFillDetector implements Detector
{
    public function skill(): string
    {
        return 'fix-at-the-source';
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->where(static fn (AstNode $node): bool => $node->isCoalesce())
            ->where(static fn (AstNode $node): bool => $node->coalesceRight()->isEmptyLiteral())
            ->where(static fn (AstNode $node): bool => $node->fillsArgument())
            ->get();
    }
}
