<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Frontend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Frontend\DeepDataReach;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Scribes\Frontend\ExtractComponentScribe;
use JesseGall\CodeCommandments\Vue\Codebase;
use JesseGall\CodeCommandments\Frontend\Detector;
use JesseGall\CodeCommandments\Vue\ElementMatch;

/**
 * Detects clusters of deep data reaches (e.g., `order.customer.name`, `order.customer.email`) sharing
 * one nested object. Returns the cluster's boundary (lowest common ancestor); lone reaches and reactive roots
 * are exempt. Points at vue-components.
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
