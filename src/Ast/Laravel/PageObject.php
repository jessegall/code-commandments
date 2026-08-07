<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Laravel;

use JesseGall\CodeCommandments\Ast\Support\DataClassShape;

use JesseGall\CodeCommandments\Ast\Codebase;

/**
 * Identifies page objects: Data classes that compose multiple nested `Data` and bind to responses.
 * Memoised per codebase.
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
