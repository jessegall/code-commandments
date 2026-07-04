<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Laravel;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Laravel\LaravelNode;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\RouteDelegatesToController;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Detects a route action that thin-wraps another route action — a controller pass-through that
 * creates redundant HTTP entry points. Gated on both the wrapper AND delegate being route actions,
 * so delegation into domain services (correct shape) is never flagged.
 */
final class RouteDelegatesToControllerDetector implements Detector
{
    public function sin(): Sin
    {
        return new RouteDelegatesToController();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereMethodDeclaration()
            ->where(static fn (LaravelNode $node): bool => $node->isRouteAction())
            ->where(static fn (LaravelNode $node): bool => $node->delegatesToRouteAction())
            ->get();
    }
}
