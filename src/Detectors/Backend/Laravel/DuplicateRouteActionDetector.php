<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Laravel;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Laravel\LaravelNode;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Ast\Laravel\RouteActions;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\DuplicateRouteAction;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Detects duplicate thin delegations to the same service across ≥2 controllers.
 * Uses type-resolved targets, distinguishing from body-hash collisions. Points at route-actions.
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
