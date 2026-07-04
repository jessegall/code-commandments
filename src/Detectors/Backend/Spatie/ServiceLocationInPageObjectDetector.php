<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Spatie\SpatieDataNode;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\ServiceLocationInPageObject;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A page object reaching into the container with `app(Service::class)` / `resolve(Service::class)`
 * inside a getter, instead of injecting the collaborator via `#[FromContainer]`. Points at page-objects.
 *
 * Overlaps the general {@see \JesseGall\CodeCommandments\Detectors\Backend\Laravel\ContainerReachDetector}
 * (service location from a container-resolved class) — and that overlap is correct: the same call is both
 * sins, but the fix differs. On a page object the collaborator can't be a plain constructor param — it must
 * be a `#[Hidden] #[FromContainer(...)]` property that `::from()` fills — so this points at the discipline
 * that teaches that shape, not the general one.
 *
 * Only a STATICALLY-KNOWN target (`app(Foo::class)`) counts; `app($runtimeString)` resolves a type unknown
 * until runtime, which `#[FromContainer]` cannot replace.
 */
final class ServiceLocationInPageObjectDetector implements Detector
{
    public function sin(): Sin
    {
        return new ServiceLocationInPageObject();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereFunction('app', 'resolve')
            ->where(static fn (AstNode $node): bool => $node->firstArgIsClassLiteral())
            ->where(static fn (SpatieDataNode $node): bool => $node->isPageObject())
            ->get();
    }
}
