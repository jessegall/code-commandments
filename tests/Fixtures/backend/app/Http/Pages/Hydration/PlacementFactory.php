<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\NullToOptionalMap;
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
     * @return list<OptCoords|Optional>
     */
    public function makeMany(array $rows): array
    {
        $out = [];

        foreach ($rows as $row) {
            $out[] = $this->one($row);
        }

        return $out;
    }

    #[Sinful(NullToOptionalMap::class)]
    public function one(?array $raw): OptCoords|Optional
    {
        return $raw !== null ? OptCoords::from($raw) : new Optional();
    }
}
