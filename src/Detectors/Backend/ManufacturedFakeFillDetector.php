<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\ManufacturedFakeFill;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;

/**
 * Detects filling an argument with a manufactured fake (empty string, zero, false) on
 * absence; real defaults like `?? 'EUR'` are legitimate.
 */
final class ManufacturedFakeFillDetector implements Detector
{
    public function sin(): Sin
    {
        return new ManufacturedFakeFill();
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
