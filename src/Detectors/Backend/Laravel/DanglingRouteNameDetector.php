<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Laravel;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Laravel\LaravelNode;
use JesseGall\CodeCommandments\Ast\Support\RouteNames;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\DanglingRouteName;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A `route('x')` / `to_route('x')` lookup whose name no registration mints. Route names are a
 * CLOSED vocabulary checked on neither side by the compiler, so a rename leaves the reference
 * pointing at nothing and it only fails when that path is walked — as a 500. Silent unless the
 * scan actually contains route registrations (otherwise the route files are simply out of scope),
 * and silent entirely in a codebase that composes any route name dynamically. Points at route-actions.
 */
final class DanglingRouteNameDetector implements Detector
{
    public function sin(): Sin
    {
        return new DanglingRouteName();
    }

    public function find(Codebase $codebase): array
    {
        $vocabulary = RouteNames::forCodebase($codebase);

        // No registration in the scan = the route files weren't judged. Nothing can be said about a
        // reference, so say nothing rather than flag every lookup in the tree.
        if (! $vocabulary->hasAny()) {
            return [];
        }

        return $codebase
            ->where(static fn (LaravelNode $node): bool => $node->routeNameReference() !== null)
            ->reject(static fn (LaravelNode $node): bool => $vocabulary->isRegistered($node->routeNameReference()))
            ->get();
    }
}
