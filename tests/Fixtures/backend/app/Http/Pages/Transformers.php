<?php

namespace Shop\Http\Pages;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\ManualOutputTransform;
use JesseGall\CodeCommandments\Testing\Fixed;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Transformers\Transformer;

/**
 * Tiny custom output transformers — each reshapes a value object into a different wire type, so the
 * generated TypeScript must be told the new shape with a `#[TypeScriptType]`. The generator cannot infer
 * a custom transformer's output; only the built-ins (`DateTimeInterfaceTransformer`, …) are known to it.
 */
#[Fixed(ManualOutputTransform::class)]
final class MoneyTransformer implements Transformer
{
    public function transform(DataProperty $property, mixed $value, TransformationContext $context): string
    {
        return number_format($value->cents / 100, 2, '.', '');
    }
}

final class GeoPointTransformer {}

final class DateRangeTransformer {}
