<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Detectors\Detector;

/**
 * Reaching into the container with `app()` / `resolve()` inside a class instead
 * of declaring the dependency in the constructor. Points at laravel-idioms.
 */
final class ContainerReachDetector implements Detector
{
    public function skill(): string
    {
        return 'laravel-idioms';
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereFunction('app', 'resolve')
            ->where(static fn (NodeMatch $match): bool => $match->enclosingClassName() !== null)
            ->get();
    }
}
