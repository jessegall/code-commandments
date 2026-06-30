<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\OptionAsNullable;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Detector;

/**
 * An `Option` worn as a nullable — `?Option` / `Option | null`, or `unwrapOr(null)`
 * collapsing it straight back to a null. Pick one model: an Option already encodes
 * absence, so nesting it in a null (or unwrapping to one) is a null in an Option
 * costume. Points at absence.
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
            ...$codebase->where(static fn (AstNode $node): bool => $node->declaresNullableOption())->get(),
            ...$codebase->where(static fn (AstNode $node): bool => $node->isUnwrapOrNull())->get(),
        ];
    }
}
