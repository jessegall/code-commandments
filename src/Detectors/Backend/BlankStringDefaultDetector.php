<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Support\ScalarRendering;
use JesseGall\CodeCommandments\Ast\TypeName;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\BlankStringDefault;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A declaration typed as a TOTAL `string` defaulting to the blank, whose own scope then asks that name
 * `=== ''` / `empty(...)` — the question is the proof that the blank is absence wearing a total type.
 * The blank counts however it is spelled: the `''` literal, or an object a `string` slot coerces to `''`,
 * so wrapping the default does not make the finding go away. It stays narrow all the same — an
 * accumulator (`$buffer = ''`) or a joiner (`$glue = ''`) means empty, never asks, and is never flagged.
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
            ->where(static fn (AstNode $node): bool => $node->isBlankString(ScalarRendering::forCodebase($codebase)))
            ->where(static fn (AstNode $node): bool => $node->isDeclarationDefault())
            ->where(static fn (AstNode $node): bool => TypeName::render($node->declaredType()) === 'string')
            ->where(static fn (AstNode $node): bool => $node->defaultedNameTestedForBlankness())
            ->get();
    }
}
