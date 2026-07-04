<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\PhpTypes;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\PhpTypes\OptionNode;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\PhpTypes\OptionAsNullable;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Detects `?Option`, `Option | null`, and `unwrapOr(null)` collapsing absence; exempt
 * in argument position, flagged only in return/assignment.
 */
final class OptionAsNullableDetector implements Detector
{
    public function sin(): Sin
    {
        return new OptionAsNullable();
    }

    public function find(Codebase $codebase): array
    {
        return [
            ...$codebase
                ->where(static fn (OptionNode $node): bool => $node->declaresNullableOption())
                ->get(),
            ...$codebase
                ->where(static fn (OptionNode $node): bool => $node->isUnwrapOrNull())
                ->reject(static fn (AstNode $node): bool => $node->fillsArgument())
                ->get(),
        ];
    }
}
