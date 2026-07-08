<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Packages;

use JesseGall\CodeCommandments\Ast\Spatie\SpatieDataNode;
use JesseGall\CodeCommandments\Packages\Tags\NoContainer;

/**
 * Spatie Laravel Data: teaches the engine that its `DataPipe`/`Cast` hooks are framework-instantiated with
 * no DI — so general rules don't mistake their per-call container reach or their contractual `$properties`
 * array for sins. The FQCNs live once, on {@see SpatieDataNode}.
 */
final class SpatiePackage extends Package
{
    public function register(Exemptions $exemptions): void
    {
        // A DataPipe/Cast is built by the framework itself, never constructor-injected: it resolves its
        // (often request-scoped) collaborators from the container per call — a cached pipe that captured a
        // scoped resolver would leak one request into the next — and receives the loose `$properties` array
        // as the pipeline's contract. Exempt from container-reach AND array-bag.
        $exemptions->exempt(NoContainer::class)->classes(...SpatieDataNode::NO_CONTAINER_CONTRACTS);
    }
}
