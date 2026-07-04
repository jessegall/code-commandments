<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Spatie\SpatieDataNode;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\TransformerWithoutTsType;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A `#[WithTransformer(SomeTransformer::class)]` on a `Data` property with NO paired `#[TypeScriptType]` /
 * `#[LiteralTypeScriptType]`. The typescript-transformer derives a property's TS type from its PHP type,
 * not from the transformer's output — so the generated frontend type silently keeps the PHP type while the
 * wire carries the transformed shape. Pairing the transformer with an explicit TS type keeps them honest.
 * Points at page-objects (where output transformers earn their place).
 *
 * A built-in transformer the generator already maps (`DateTimeInterfaceTransformer`, `ArrayableTransformer`)
 * is exempt — its target TS type is known without an annotation.
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
