<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Frontend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Frontend\DeepDataReach;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Scribes\Frontend\ExtractComponentScribe;
use JesseGall\CodeCommandments\Vue\Codebase;
use JesseGall\CodeCommandments\Vue\Detector;
use JesseGall\CodeCommandments\Vue\ElementMatch;

/**
 * A CLUSTER of deep data reaches that share one nested object — an element binding or
 * interpolating `order.customer.name`, `order.customer.email`, … from several places
 * in a sizeable template. Those elements all know the whole shape of `order`; that's
 * Law of Demeter in the markup, and the shared object (`order.customer`) wants to be
 * its own component taking the mid-object as a prop. Points at vue-components.
 *
 * The finding is the cluster's BOUNDARY — the lowest common ancestor of the reaches,
 * the element the extract-scribe lifts — not each leaf. A lone deep reach, a reach
 * into a `v-model`-bound (reactive) root, or a reach in a tiny component is NOT a sin;
 * the rulebook lives in {@see DeepReachCluster}. Depth, chains and the reactive-root
 * test are all read off the parsed JS expression AST, never a regex or a name list.
 */
final class DeepDataReachDetector implements Detector, Repentable
{
    private const int MIN_TEMPLATE_LINES = 50;

    public function sin(): Sin
    {
        return new DeepDataReach();
    }

    public function scribe(): ExtractComponentScribe
    {
        return ExtractComponentScribe::forDeepReach();
    }

    public function find(Codebase $components): array
    {
        // Compose the query for the candidates — deep-reaching elements in sizeable templates
        // — then group them into clusters and take each cluster's boundary.
        $candidates = $components
            ->whereElement()
            ->inTemplateOfAtLeast(self::MIN_TEMPLATE_LINES)
            ->reachesAtLeast(DeepReachCluster::MIN_DEPTH, DeepReachCluster::TRANSPARENT)
            ->get();

        $findings = [];

        foreach (DeepReachCluster::cluster($candidates) as $cluster) {
            $boundary = $cluster->boundary();

            // A cluster spanning the whole template is too diffuse to be one component.
            if (! $boundary->isRoot()) {
                $findings[] = new ElementMatch($boundary, $cluster->sfc);
            }
        }

        return $findings;
    }
}
