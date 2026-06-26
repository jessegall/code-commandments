<?php

namespace Shop\Orders;

use JesseGall\CodeCommandments\Detectors\Backend\FeatureEnvyDetector;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Feature envy via an external query over a NESTED object's collection: it reaches
 * `$context->descriptor`, exports its handle names, and runs the membership test
 * out here — that question belongs on the descriptor (`$descriptor->hasBranch()`).
 */
final class BranchGuard
{
    #[Sinful(FeatureEnvyDetector::class)]
    public function permits(RoutingContext $context, string $branch): bool
    {
        return in_array($branch, $context->descriptor->handleNames(), true);
    }
}
