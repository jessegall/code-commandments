<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Spatie\SpatieDataNode;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\ManualOutputTransform;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Flags a `Data` slot that hand-flattens one value object into a wire array — a getter hook, a
 * `#[Computed]` method, or a constructor assignment — where a `#[WithTransformer]` should own the shape.
 */
final class ManualOutputTransformDetector implements Detector
{
    public function sin(): Sin
    {
        return new ManualOutputTransform();
    }

    public function find(Codebase $codebase): array
    {
        $flattensValueObject = static fn (SpatieDataNode $node): bool =>
            $node->isDataClass() && $node->flattensValueObjectToArray();

        return [
            ...$codebase->whereGetterHook()->where($flattensValueObject)->get(),
            ...$codebase->whereMethodDeclaration()
                ->where(static fn (AstNode $node): bool => $node->hasAttribute('Computed'))
                ->where($flattensValueObject)
                ->get(),
            ...$codebase->whereAssign()->where($flattensValueObject)->get(),
        ];
    }
}
