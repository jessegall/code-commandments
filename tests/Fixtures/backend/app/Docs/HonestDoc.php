<?php

namespace Shop\Docs;

use JesseGall\CodeCommandments\Sins\Backend\DanglingDocReference;
use JesseGall\CodeCommandments\Sins\Backend\BareStatePredicate;
use JesseGall\CodeCommandments\Sins\Backend\InlineDocblock;
use JesseGall\CodeCommandments\Sins\Backend\StackedDocblock;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * Righteous twin: its cross-references resolve — a first-party {@see \Shop\Catalog\SkuRegistry} that exists,
 * and a vendor {@see \Illuminate\Support\Collection} that lives in another package (left unverified). Neither
 * dangles, so it must NOT flag.
 */
#[Righteous(DanglingDocReference::class)]
#[Righteous(BareStatePredicate::class)]
#[Righteous(InlineDocblock::class)]
#[Righteous(StackedDocblock::class)]
final class HonestDoc
{
    /**
     * A question about its own state — the mood a bool is supposed to wear.
     */
    public function isTallied(): bool
    {
        return true;
    }

    /**
     * A relational predicate: it takes what it compares against, so the third person is correct.
     *
     * @param string $needle The word to look for.
     */
    public function contains(string $needle): bool
    {
        return $needle !== '';
    }

    /**
     * The count, floored at zero.
     *
     * @param int $items How many were seen.
     */
    public function tally(int $items): int
    {
        return max(0, $items);
    }
}
