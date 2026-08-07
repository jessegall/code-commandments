<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Laravel;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\ContainerReach;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Support\Container;
use JesseGall\CodeCommandments\Backend\AppliesExemptions;
use JesseGall\CodeCommandments\Packages\ExemptBy;
use JesseGall\CodeCommandments\Packages\Exemptable;
use JesseGall\CodeCommandments\Packages\Tags\NoContainer;

/**
 * Reaching into the container with `app()`/`resolve()` from a class the container
 * resolves — the dependency belongs in the constructor. Only statically-known targets
 * count; suppressed for enums and hand-instantiated classes. A `NoContainer` type the
 * framework `new`s itself with no DI (an Eloquent cast) is exempt — it CAN'T
 * constructor-inject, so per-call container reach is its convention. A class resolving
 * ITSELF (`app(static::class)` in a static `make()`) is exempt — that is the construction
 * seam that gives a static entry point constructor DI, not a dependency reach.
 */
final class ContainerReachDetector implements Detector, Exemptable
{
    use AppliesExemptions;

    public function sin(): Sin
    {
        return new ContainerReach();
    }

    public function exemptions(): array
    {
        return [NoContainer::class => [ExemptBy::EnclosingClass]];
    }

    public function find(Codebase $codebase): array
    {
        return $this->exempt($codebase
            ->whereFunction('app', 'resolve')
            ->reject(static fn (AstNode $node): bool => $node->isInEnum())
            ->where(static fn (AstNode $node): bool => $node->firstArgIsClassLiteral())
            ->reject(static fn (AstNode $node): bool => $node->isEnclosingClassResolution())
            ->where(static fn (AstNode $node) => Container::resolves($codebase, $node->enclosingClassName()))
            ->get(), $codebase);
    }
}
