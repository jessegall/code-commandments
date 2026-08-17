<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Support\ScalarRendering;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\ErasedNullObject;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A `new X` whose `__toString` renders `''`, written where the declared type is `string` — a default or a
 * return. The type coerces it on the way in, so the slot holds `''` and the object is gone: it is the
 * blank with ceremony, reading as though absence had been modelled while every consumer still decodes a
 * blank. A Null Object earns its keep only where the type admits the object.
 */
final class ErasedNullObjectDetector implements Detector
{
    public function sin(): Sin
    {
        return new ErasedNullObject();
    }

    public function find(Codebase $codebase): array
    {
        $rendering = ScalarRendering::forCodebase($codebase);

        return $codebase
            ->whereNew()
            ->where(static fn (AstNode $node): bool => $rendering->isBlank($node->newClassName()))
            ->where(static fn (AstNode $node): bool => $node->fillsSlotTyped('string'))
            ->get();
    }
}
