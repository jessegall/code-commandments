<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\DataCollectionType;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

/*
 * Scenario 3 — an activity feed exposing its items through a computed `DataCollection` get-hook. The type
 * is still wrong (should be `array` + `#[DataCollectionOf]`); a computed-collection shape.
 */
#[Sinful(DataCollectionType::class)]
final class ActivityFeed extends Data
{
    public function __construct(public readonly string $cursor) {}

    /** @var DataCollection<int, FeedItem> */
    #[Computed]
    public DataCollection $items { get => FeedItem::collect([], DataCollection::class); }

    public function opensAtStart(): bool
    {
        return $this->cursor === '';
    }
}

final class FeedItem extends Data
{
    public function __construct(public readonly string $verb) {}
}
