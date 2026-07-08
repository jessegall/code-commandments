<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Laravel;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\ContainerReach;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Support\Container;
use JesseGall\CodeCommandments\Packages\Exemptable;
use JesseGall\CodeCommandments\Packages\Exemptions;
use JesseGall\CodeCommandments\Packages\Tags\NoContainer;

/**
 * Reaching into the container with `app()`/`resolve()` from a class the container
 * resolves — the dependency belongs in the constructor. Only statically-known targets
 * count; suppressed for enums and hand-instantiated classes. A `NoContainer` type the
 * framework builds itself with no DI (an Eloquent cast, a Spatie `DataPipe`/`Cast`) is
 * exempt — it CAN'T constructor-inject, so per-call container reach is its convention.
 */
final class ContainerReachDetector implements Detector, Exemptable
{
    public function sin(): Sin
    {
        return new ContainerReach();
    }

    public function exemptions(): array
    {
        return [NoContainer::class];
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereFunction('app', 'resolve')
            ->reject(static fn (AstNode $node): bool => $node->isInEnum())
            ->where(static fn (AstNode $node): bool => $node->firstArgIsClassLiteral())
            ->where(static fn (AstNode $node): bool => Container::resolves($codebase, $node->enclosingClassName()))
            ->reject(static fn (AstNode $node): bool => Exemptions::has(NoContainer::class, $codebase, $node->enclosingClassName()))
            ->get();
    }
}
