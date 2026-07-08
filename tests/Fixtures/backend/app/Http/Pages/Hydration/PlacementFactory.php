<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\NullToOptionalMap;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\PreferOptionalCreate;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Optional;

/*
 * Hydrates a batch of rows, mapping each absent one onto `Optional` with a value-first ternary — the mirror
 * ordering, a distinct producer shape.
 */
final class PlacementFactory
{
    /**
     * @param  list<?array>  $rows
     * @return list<OptRange|Optional>
     */
    public function makeMany(array $rows): array
    {
        $out = [];

        foreach ($rows as $row) {
            $out[] = $this->one($row);
        }

        return $out;
    }

    /**
     * @param  list<OptRange|Optional>  $mapped
     * @return list<OptRange>
     */
    public function present(array $mapped): array
    {
        return array_values(array_filter($mapped, static fn (OptRange|Optional $entry): bool => ! $entry instanceof Optional));
    }

    #[Sinful(NullToOptionalMap::class)]
    #[Sinful(PreferOptionalCreate::class)]
    public function one(?array $raw): OptRange|Optional
    {
        return $raw !== null ? OptRange::from($raw) : new Optional();
    }
}
