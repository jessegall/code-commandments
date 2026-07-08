<?php

namespace Shop\Docs;

use JesseGall\CodeCommandments\Sins\Backend\DanglingDocReference;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * Righteous twin: its cross-references resolve — a first-party {@see \Shop\Catalog\SkuRegistry} that exists,
 * and a vendor {@see \Illuminate\Support\Collection} that lives in another package (left unverified). Neither
 * dangles, so it must NOT flag.
 */
#[Righteous(DanglingDocReference::class)]
final class HonestDoc
{
    public function tally(int $items): int
    {
        return max(0, $items);
    }
}
