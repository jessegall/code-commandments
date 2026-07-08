<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\NullToOptionalMap;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Optional;

/*
 * The map re-derived with the PREFERRED `Optional::create()` (not a raw `new Optional`) — proving the sin is
 * the null→Optional MAP itself, not the construction style, so adopting `Optional::create()` can't smuggle it
 * past the detector. Two distinct producer shapes: a `??` over an already-built value and a null-first ternary
 * hydrating a concrete OTHER type. Neither trips PreferOptionalCreate (there is no raw `new` here), so each is
 * marked for THIS sin only.
 */
final class AbsentTagProbe
{
    private ?OptTag $memo = null;

    #[Sinful(NullToOptionalMap::class)]
    public function latest(): OptTag|Optional
    {
        return $this->memo ?? Optional::create();
    }

    #[Sinful(NullToOptionalMap::class)]
    public function build(?array $raw): OptCoords|Optional
    {
        return $raw === null ? Optional::create() : OptCoords::from($raw);
    }
}
