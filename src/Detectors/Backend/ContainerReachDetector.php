<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Detector;

/**
 * Reaching into the container with `app()` / `resolve()` from a class the
 * container itself resolves — the dependency belongs in the constructor.
 * Points at laravel-idioms.
 *
 * Suppressed where the container can't build the class, so app()/resolve() is
 * the only option: an enum (never constructible), or a class only ever
 * instantiated by hand (its constructor isn't filled by the container).
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
            ->reject(static fn (AstNode $node): bool => $node->isInEnum())
            ->where(fn (AstNode $node): bool => $this->isContainerResolved($codebase, $node))
            ->get();
    }

    private function isContainerResolved(Codebase $codebase, AstNode $node): bool
    {
        $class = $node->enclosingClassName();

        return $class !== null
            && ($this->injectedAsDependency($codebase, $class) || $this->neverInstantiatedByHand($codebase, $class));
    }

    private function injectedAsDependency(Codebase $codebase, string $class): bool
    {
        return $codebase->whereParamType($class)
            ->where(static fn (AstNode $node): bool => $node->enclosingFunctionName() === '__construct')
            ->count() > 0;
    }

    private function neverInstantiatedByHand(Codebase $codebase, string $class): bool
    {
        return $codebase->whereNew($class)->count() === 0;
    }
}
