<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Laravel;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Laravel\LaravelNode;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\FacadeCall;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A Laravel facade call — `Cache::get(...)`, `Log::info(...)`. A facade is a global reach into the
 * container wearing a static-method face: it hides the dependency, can't be substituted, and ties
 * the class to the framework. Inject the underlying contract instead. Points at laravel-idioms.
 *
 * Exempt: a `::fake()` test double (no instance form to inject); a call OUTSIDE any class (a route
 * or config file has nothing to inject into); a `ServiceProvider` (wiring at boot is its job); and
 * an Eloquent CAST (Eloquent `new`-instantiates it with no container) — each detected by the AST,
 * not a name.
 */
final class FacadeCallDetector implements Detector
{
    public function sin(): Sin
    {
        return new FacadeCall();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereStaticCall()
            ->where(static fn (LaravelNode $node): bool => $node->isFacadeCall())
            ->reject(static fn (AstNode $node): bool => $node->staticCallMethodIs('fake'))
            ->reject(static fn (AstNode $node): bool => $node->isOutsideClass())
            ->reject(static fn (LaravelNode $node): bool => $node->inServiceProvider())
            ->reject(static fn (LaravelNode $node): bool => $node->isEloquentCast())
            ->get();
    }
}
