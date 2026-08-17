<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\TypeName;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\BlankStringDefault;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A declaration typed as a TOTAL `string` that defaults to `''`, whose own scope then asks that name
 * `=== ''` / `empty(...)` — the question is the proof that `''` is absence wearing a total type. It is
 * also what keeps the rule narrow: an accumulator (`$buffer = ''`) or a joiner (`$glue = ''`) starts
 * empty and MEANS empty, never asks, and so is never flagged.
 */
final class BlankStringDefaultDetector implements Detector
{
    public function sin(): Sin
    {
        return new BlankStringDefault();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereString()
            ->where(static fn (AstNode $node): bool => $node->isEmptyString())
            ->where(static fn (AstNode $node): bool => $node->isDeclarationDefault())
            ->where(static fn (AstNode $node): bool => TypeName::render($node->declaredType()) === 'string')
            ->where(static fn (AstNode $node): bool => $node->defaultedNameTestedForBlankness())
            ->get();
    }
}
