<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Sins\Backend\Laravel\ConfigRead;
use JesseGall\CodeCommandments\Testing\Fixed;

/**
 * The config values the catalog actually uses, read once at the edge and typed. Every body that
 * used to reach for `config()` takes this instead, and gets an `int` rather than a `mixed`.
 */
#[Fixed(ConfigRead::class)]
final class CatalogSettings
{
    public function __construct(
        public readonly int $perPage = 24,
    ) {}
}
