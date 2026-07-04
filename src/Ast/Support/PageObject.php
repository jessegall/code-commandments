<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use JesseGall\CodeCommandments\Ast\Codebase;

/**
 * A PAGE OBJECT — the single composed `Data` a controller sends back for one page to render. Two
 * properties, together, tell it apart from an ordinary DTO:
 *
 *  1. it COMPOSES more than one nested `Data` ({@see DataClassShape::composesMultipleData()}) — a payload
 *     assembled from smaller slots, not a leaf; and
 *  2. it TRAVELS BACK in a response ({@see ResponseSurface::isResponseBound()}) — an internal aggregate,
 *     however large, is not a page object.
 *
 * Callers first establish the class IS `Data` (the detector's own `isDataClass()` gate); this policy adds
 * the two page-object conditions. Memoised per codebase — it only composes the two shared analyses.
 */
final class PageObject
{
    private static ?\WeakMap $memo = null;

    private function __construct(
        private readonly Codebase $codebase,
        private readonly DataClassShape $shape,
        private readonly ResponseSurface $surface,
    ) {}

    public static function forCodebase(Codebase $codebase): self
    {
        self::$memo ??= new \WeakMap();

        return self::$memo[$codebase] ??= new self(
            $codebase,
            DataClassShape::forCodebase($codebase),
            ResponseSurface::forCodebase($codebase),
        );
    }

    /**
     * Is the named class a page object — does it compose more than one nested `Data` AND travel back in a
     * response? The `Data`-ness of the class is the caller's precondition.
     */
    public function isPageObject(?string $fqcn): bool
    {
        return $this->shape->composesMultipleData($fqcn, $this->codebase)
            && $this->surface->isResponseBound($fqcn);
    }
}
