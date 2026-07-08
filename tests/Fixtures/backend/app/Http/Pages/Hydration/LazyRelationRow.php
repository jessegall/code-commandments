<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\NullToOptionalMap;
use JesseGall\CodeCommandments\Testing\Righteous;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/*
 * Righteous twin: an `Optional` in a ternary arm whose condition is a BOOLEAN guard, not a null-check
 * (`$row->relationLoaded('range') ? $row->range : Optional::create()`) — the idiomatic "omit an unloaded
 * relation from the wire" pattern. It is NOT a hand-rolled null→Optional map (the scaffolded factory maps a
 * null PAYLOAD via `::from`, which this isn't), so it must NOT flag. Only a null-guard ternary is the map.
 */
#[Righteous(NullToOptionalMap::class)]
final class LazyRelationRow extends Data
{
    public function __construct(
        public readonly string $label,
        public readonly OptRange|Optional $range = new Optional(),
    ) {}

    public static function fromRow(object $row): self
    {
        return self::from([
            'label' => $row->label,
            'range' => $row->relationLoaded('range') ? $row->range : Optional::create(),
        ]);
    }
}
