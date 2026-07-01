<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Sins\Backend\NegativeSpaceComment;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/** A keyed store of per-code catalog entries. */
final class SkuRegistry
{
    #[Righteous(NegativeSpaceComment::class)]
    public function has(string $code): bool
    {
        // a random probe key still reads cleanly through the same path
        return $code !== '';
    }

    #[Sinful(NegativeSpaceComment::class)]
    public function get(string $code): SkuEntry
    {
        // no magic here; a missing code yields an empty entry
        return new SkuEntry();
    }
}
