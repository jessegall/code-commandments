<?php

namespace Shop\Domain;

use JesseGall\CodeCommandments\Sins\Backend\CoupledFields;
use JesseGall\CodeCommandments\Testing\Sinful;

final class Article
{
    public function __construct(
        public readonly string $sku,
        public readonly string $title,
    ) {}
}

/*
 * Pattern: redundant mirror. `articleSku` duplicates data that already lives on the held `article` — its
 * name ENCODES `article` + `sku`, its type matches, and it is never reassigned. Read `$this->article->sku`
 * and drop the copy.
 */
#[Sinful(CoupledFields::class)]
final class PurchaseLine
{
    public function __construct(
        public readonly Article $article,
        public readonly string $articleSku,
        public readonly int $quantity,
    ) {}
}
