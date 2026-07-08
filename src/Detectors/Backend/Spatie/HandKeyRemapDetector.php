<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Spatie\SpatieDataNode;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\HandKeyRemap;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Flags `SomeData::from(['camelKey' => $src['snake_key'], …])` — a hand-written snake→camel key translation
 * off one source array that a class-level `#[MapInputName(SnakeCaseMapper::class)]` + `::from($src)` owns
 * once. Every value must be a bare `$src['snake']` whose key is exactly its camelCase, so a transformed
 * value or a non-mechanical mapping is spared.
 */
final class HandKeyRemapDetector implements Detector
{
    public function sin(): Sin
    {
        return new HandKeyRemap();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereStaticCall('from')
            ->where(static fn (SpatieDataNode $n): bool => $n->isHandKeyRemap())
            ->get();
    }
}
