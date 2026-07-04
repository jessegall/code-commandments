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
 * Detects page objects using service location (`app()`, `resolve()`) instead of injecting
 * via `#[FromContainer]` attributes.
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
