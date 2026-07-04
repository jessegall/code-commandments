<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Laravel;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Laravel\LaravelNode;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\RouteDelegatesToController;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A route action that is a THIN pass-through to ANOTHER controller — its whole body a single
 * `return $this->otherController->action(...);` where the receiver is a different class whose method is
 * itself a route action. A controller wrapping a controller is a redundant HTTP entry point onto an
 * operation that already has a home. Points at route-actions.
 *
 * Gated on the route-action identity (the union of route-file registration, a request-handling signature,
 * and response-reachability), and on the delegate ALSO being a route action — so an action that thinly
 * delegates into a domain SERVICE (the correct shape) is never flagged.
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
