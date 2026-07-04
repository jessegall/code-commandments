<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Laravel;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Laravel\LaravelNode;
use JesseGall\CodeCommandments\Ast\Support\RouteActions;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\DuplicateRoute;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Two-or-more route registrations of the SAME verb that bind different URLs to the SAME
 * `[Controller, method]` action — two names for one handler, whose middleware/names/constraints drift
 * apart over time. Points at route-actions.
 *
 * Pure route-file analysis: every `Route::verb(...)` / `$router->verb(...)` carrying a `[Controller,
 * method]` action, grouped by `verb + action`. Grouping by verb keeps a deliberate GET+POST pair on one
 * URL out of it. INVOKABLE single-action controllers are exempt — mapping one to several canonical URLs
 * (well-known/discovery endpoints, aliases, `/{path}` nested variants) is idiomatic, not a duplicate.
 */
final class DuplicateRouteDetector implements Detector
{
    public function sin(): Sin
    {
        return new DuplicateRoute();
    }

    public function find(Codebase $codebase): array
    {
        $registrations = [
            ...$codebase->whereStaticCall(...LaravelNode::ROUTE_VERBS)->get(),
            ...$codebase->whereMethod(...LaravelNode::ROUTE_VERBS)->get(),
        ];

        $byRoute = [];

        foreach ($registrations as $match) {
            $verb = RouteActions::verbOf($match->node);

            if ($verb === null) {
                continue;
            }

            foreach (RouteActions::actionsOf($match->node) as $action) {
                if (str_ends_with($action, '::__invoke')) {
                    // An invokable single-action controller is ONE operation by design; mapping it to
                    // several canonical URLs (discovery/well-known endpoints, aliases, `/{path}` nested
                    // variants) is idiomatic, not a duplicate. Only a repeated `[Controller, method]`
                    // array binding is the redundant handler this flags.
                    continue;
                }

                $byRoute[$verb . ' ' . $action][] = $match;
            }
        }

        $findings = [];

        foreach ($byRoute as $matches) {
            if (count($matches) >= 2) {
                array_push($findings, ...$matches);
            }
        }

        return $findings;
    }
}
