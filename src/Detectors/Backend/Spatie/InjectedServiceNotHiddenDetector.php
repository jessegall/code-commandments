<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Spatie\SpatieDataNode;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\InjectedServiceNotHidden;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A page object that injects a collaborator into a PUBLIC property without `#[Hidden]`. The service —
 * a `#[FromContainer]` repository, projector, or the request — is construction machinery, not page
 * data; left public and un-hidden it serializes AND leaks into the generated TypeScript type for the
 * page. `#[Hidden]` is the only thing that keeps it out of both. Points at page-objects.
 *
 * Gated on the page-object identity (a composed, response-bound `Data`), so it never fires on an
 * ordinary DTO. A non-public injected property is exempt — it never serialized to begin with.
 */
final class InjectedServiceNotHiddenDetector implements Detector
{
    public function sin(): Sin
    {
        return new InjectedServiceNotHidden();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereClass()
            ->where(static fn (SpatieDataNode $node): bool => $node->isPageObject())
            ->where(static fn (SpatieDataNode $node): bool => $node->hasUnhiddenInjectedService())
            ->get();
    }
}
