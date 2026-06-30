<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\ContainerReach;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Detector;
use JesseGall\CodeCommandments\Detectors\Support\Container;

/**
 * Reaching into the container with `app()` / `resolve()` from a class the
 * container itself resolves — the dependency belongs in the constructor.
 * Points at laravel-idioms.
 *
 * Suppressed where the container can't build the class, so app()/resolve() is
 * the only option: an enum (never constructible), or a class only ever
 * instantiated by hand (its constructor isn't filled by the container).
 *
 * Only a STATICALLY-KNOWN target counts — `app(Foo::class)` / `app('binding')`.
 * `app($runtimeClassString)` resolves a type unknown until runtime, which
 * constructor DI genuinely cannot replace, so it is not a sin.
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
