<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Detector;

/**
 * `<Data>::from(...)` called inside a loop — hydrating a collection one item at a
 * time. Spatie does this in one pass: a `#[DataCollectionOf]` property plus
 * `::collect()`. The loop is the mapping that belongs to the framework. Points at
 * spatie-data.
 */
final class ManualHydrationLoopDetector implements Detector
{
    private const string DATA = 'Spatie\\LaravelData\\Data';

    public function skill(): string
    {
        return 'spatie-data';
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereStaticCall('from')
            ->where(static fn (AstNode $node): bool => $codebase->extends($node->staticCallClass(), self::DATA))
            ->where(static fn (AstNode $node): bool => $node->isWithinLoop())
            ->get();
    }
}
