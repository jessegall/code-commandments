<?php

namespace Shop\Http\Pages\FlatCluster;

use Spatie\LaravelData\Data;

/* The Broadcast value object: a realtime channel, {url, enabled, cursor}. */
final class Broadcast extends Data
{
    public function __construct(
        public readonly string $url,
        public readonly bool $enabled,
        public readonly int $cursor,
    ) {}
}
