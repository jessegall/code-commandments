<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Spatie\SpatieDataNode;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\InjectedServiceNotHidden;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Detects injected services in public properties without #[Hidden] on page objects;
 * non-public properties are exempt.
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
