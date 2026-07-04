<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Laravel;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Laravel\LaravelNode;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Ast\Support\RouteActions;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\DuplicateRouteAction;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Two-or-more route actions, in DIFFERENT controllers, that thinly delegate to the SAME operation — each
 * a `return $this->exporter->export(...)` resolving to the same `WorkflowExporter::export`. They are the
 * same entry point twice: one operation deserving one way in. Points at route-actions.
 *
 * The key is the type-resolved delegation TARGET (not a body hash), so a coincidental property name can't
 * collide two unrelated thin actions. A target that is itself a registered controller action is left to
 * {@see RouteDelegatesToControllerDetector} (that's a controller wrapping a controller); this catches the
 * shared-service case it doesn't. Only groups spanning ≥2 controllers count.
 */
final class DuplicateRouteActionDetector implements Detector
{
    public function sin(): Sin
    {
        return new DuplicateRouteAction();
    }

    public function find(Codebase $codebase): array
    {
        $routes = RouteActions::forCodebase($codebase);
        $byTarget = [];

        $actions = $codebase
            ->whereMethodDeclaration()
            ->where(static fn (LaravelNode $node): bool => $node->isRouteAction())
            ->get();

        foreach ($actions as $match) {
            $laravel = $codebase->wrap($match->node, $match->file, LaravelNode::class);
            \assert($laravel instanceof LaravelNode);
            $target = $laravel->thinDelegationTarget();

            if ($target === null || $this->targetIsController($routes, $target)) {
                continue;
            }

            $byTarget[$target][] = $match;
        }

        $findings = [];

        foreach ($byTarget as $matches) {
            if ($this->spansDistinctControllers($matches)) {
                array_push($findings, ...$matches);
            }
        }

        return $findings;
    }

    private function targetIsController(RouteActions $routes, string $target): bool
    {
        [$class, $method] = explode('::', $target, 2);

        return $routes->isRegisteredAction($class, $method);
    }

    /**
     * @param  list<NodeMatch>  $matches
     */
    private function spansDistinctControllers(array $matches): bool
    {
        $classes = [];

        foreach ($matches as $match) {
            $classes[(string) $match->enclosingClassName()] = true;
        }

        return count($classes) >= 2;
    }
}
