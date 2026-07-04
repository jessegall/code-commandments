<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Spatie\SpatieDataNode;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\TransformerWithoutTsType;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Flags a custom `#[WithTransformer]` on a `Data` property with no paired `#[TypeScriptType]` /
 * `#[LiteralTypeScriptType]`, so the generated TS keeps the PHP type while the wire carries the
 * transformed shape. Built-in transformers the generator already maps are exempt.
 */
final class TransformerWithoutTsTypeDetector implements Detector
{
    public function sin(): Sin
    {
        return new TransformerWithoutTsType();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereAttribute('WithTransformer')
            ->where(static fn (SpatieDataNode $node): bool => $node->isDataClass())
            ->where(static fn (SpatieDataNode $node): bool => $node->transformerLacksTsType())
            ->get();
    }
}
