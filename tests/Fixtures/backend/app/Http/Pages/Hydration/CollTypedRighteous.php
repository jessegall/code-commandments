<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\DataCollectionType;
use JesseGall\CodeCommandments\Testing\Righteous;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

/*
 * Righteous twin for DataCollectionType — the collection is typed `array` with `#[DataCollectionOf]`, the
 * correct shape. Must NOT flag.
 */
#[Righteous(DataCollectionType::class)]
final class GalleryPage extends Data
{
    /** @param list<Photo> $photos */
    public function __construct(
        public readonly string $album,
        #[DataCollectionOf(Photo::class)]
        public readonly array $photos = [],
    ) {}
}

final class Photo extends Data
{
    public function __construct(public readonly string $url) {}
}
