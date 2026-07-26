<?php

namespace Shop\Events;

/**
 * The legacy supplier feed finished importing — the importer that fired this is long gone.
 */
final readonly class FeedImported
{
    public function __construct(public int $rows) {}
}
