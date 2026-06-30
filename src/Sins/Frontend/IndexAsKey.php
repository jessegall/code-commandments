<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Frontend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Frontend\VueControlFlow;

final class IndexAsKey extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'index-as-key',
            skill: VueControlFlow::class,
            description: "`:key` bound to the `v-for` index — a positional key corrupts state when the list reorders or an item is inserted",
            rule: "Key a `v-for` by a STABLE identity (`:key=\"item.id\"`), never the loop index."
        );
    }
}
