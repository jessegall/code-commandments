<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\RedundantNestedFrom;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

/*
 * N1 scenario 2 — a list literal into a `#[DataCollectionOf]`; every element wrapper auto-hydrates. A fixed
 * tab vocabulary the surface renders, assembled inline.
 */
final class TabBar extends Data
{
    public function __construct(
        #[DataCollectionOf(TabCopy::class)]
        public readonly array $tabs,
    ) {}
}

final class TabBarComposer
{
    #[Sinful(RedundantNestedFrom::class)]
    public function compose(): TabBar
    {
        return TabBar::from(['tabs' => [
            TabCopy::from(['id' => 'edit', 'title' => 'Edit']),
            TabCopy::from(['id' => 'preview', 'title' => 'Preview']),
        ]]);
    }
}
