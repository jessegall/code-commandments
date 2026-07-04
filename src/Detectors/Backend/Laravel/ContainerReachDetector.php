<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Laravel;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\ContainerReach;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Support\Container;

/**
 * Reaching into the container with `app()`/`resolve()` from a class the container
 * resolves — the dependency belongs in the constructor. Only statically-known targets
 * count; suppressed for enums and hand-instantiated classes.
 */
final class ContainerReachDetector implements Detector
{
    public function sin(): Sin
    {
        return new ContainerReach();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereFunction('app', 'resolve')
            ->reject(static fn (AstNode $node): bool => $node->isInEnum())
            ->where(static fn (AstNode $node): bool => $node->firstArgIsClassLiteral())
            ->where(static fn (AstNode $node): bool => Container::resolves($codebase, $node->enclosingClassName()))
            ->get();
    }
}
