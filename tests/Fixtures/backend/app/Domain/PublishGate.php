<?php

namespace Shop\Domain;

use JesseGall\CodeCommandments\Sins\Backend\RepeatedGuard;
use JesseGall\CodeCommandments\Testing\Sinful;

/*
 * CROSS-CLASS half of a repeated guard: `$item->published && $item->approved` lives here in `PublishGate`
 * AND, verbatim, over in `ReviewQueue` — two DIFFERENT classes in two DIFFERENT files. The detector buckets
 * by fingerprint across the whole codebase, so a copied guard is caught even when the copies never share a
 * class. One marker here, one there.
 */
final class PublishGate
{
    #[Sinful(RepeatedGuard::class)]
    public function visible($item): bool
    {
        return $item->published && $item->approved;
    }

    public function slugFor(string $title): string
    {
        return trim(str_replace(' ', '-', strtolower($title)), '-');
    }

    public function wordCount(string $body): int
    {
        return count(explode(' ', trim($body)));
    }
}
