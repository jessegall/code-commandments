<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\CancelledCoalesce;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;

/**
 * A manufactured fake compared against itself — `($x ?? '') !== ''`. Absent and empty reach the same
 * branch, so the reader cannot tell which the author meant and the code never records the
 * difference. Sibling to `ManufacturedFakeFill`, sharing its first two checks: there the fake is
 * PASSED ON, here it never leaves the expression. Two fallbacks are excluded for the reasons the
 * sibling excludes them: `null` is not a fake but the absence itself, so `($a[$k] ?? null) === null`
 * is an honest read of a key that may not be there; and `[]` is a collection's own identity rather
 * than a scalar impersonating data, so `($a[$k] ?? []) === []` asks ONE question — "no items" —
 * however the value got there (#398). Points at absence.
 */
final class CancelledCoalesceDetector implements Detector
{
    public function sin(): Sin
    {
        return new CancelledCoalesce();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->where(static fn (AstNode $node): bool => $node->isCoalesce())
            ->where(static fn (AstNode $node): bool => $node->coalesceRight()->isEmptyLiteral())
            ->reject(static fn (AstNode $node): bool => $node->coalesceRight()->isEmptyArrayLiteral())
            ->where(static fn (AstNode $node): bool => $node->isCancelledCoalesce())
            ->get();
    }
}
