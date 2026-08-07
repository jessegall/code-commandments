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
 * PASSED ON, here it never leaves the expression. `?? null` is excluded by the same rule that
 * excludes it there — `null` is not a fake, it is the absence itself, so `($a[$k] ?? null) === null`
 * is an honest read of a key that may not be there. Points at absence.
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
            ->where(static fn (AstNode $node): bool => $node->isCancelledCoalesce())
            ->get();
    }
}
