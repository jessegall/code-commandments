<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Packages;

use JesseGall\CodeCommandments\Ast\Spatie\SpatieDataNode;
use JesseGall\CodeCommandments\Packages\Tags\NoContainer;

/**
 * Spatie Laravel Data. A `DataPipe`/`Cast` is built ONCE and CACHED by the framework, then reused across
 * calls and requests — a constructor-injected (often request-scoped) collaborator would go stale and leak
 * one request into the next, so a pipe/cast resolves its collaborators from the container per call and takes
 * the loose `$properties` array as the pipeline's contract. Both are the framework's convention, not sins.
 */
final class SpatiePackage extends Package
{
    public function register(Exemptions $exemptions): void
    {
        $exemptions->exempt(NoContainer::class)->classes(...SpatieDataNode::NO_CONTAINER_CONTRACTS);
    }
}
