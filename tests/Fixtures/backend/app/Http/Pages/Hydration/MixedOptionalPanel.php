<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\AllOptionalData;
use JesseGall\CodeCommandments\Testing\Righteous;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/*
 * Righteous twin for AllOptionalData — a required core field (`$id`) gives the object its identity, so
 * some Optional fields alongside it are honest, not the all-optional envelope smell. Must NOT flag.
 */
#[Righteous(AllOptionalData::class)]
final class Panel extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string|Optional $title = new Optional(),
        public readonly string|Optional $subtitle = new Optional(),
    ) {}
}
