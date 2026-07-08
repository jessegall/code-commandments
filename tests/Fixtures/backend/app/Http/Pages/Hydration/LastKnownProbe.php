<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\NullToOptionalMap;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Optional;

/*
 * A memoised probe: it remembers the last coordinate and maps a cache miss onto `Optional` with a `??`
 * coalesce — a third distinct shape.
 */
final class LastKnownProbe
{
    private ?OptCoords $memo = null;

    public function warm(OptCoords $coords): void
    {
        $this->memo = $coords;
    }

    #[Sinful(NullToOptionalMap::class)]
    public function latest(): OptCoords|Optional
    {
        return $this->memo ?? new Optional;
    }
}
