<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\NullToOptionalMap;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\PreferOptionalCreate;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Optional;

/*
 * A request holding a raw, possibly-absent position and hand-mapping its absence onto `Optional` with a
 * null-first ternary. The `optionalOrMissing()` factory is the one named home for the map.
 */
final class MoveRequest
{
    /**
     * @param  array<string, mixed>|null  $rawPosition
     */
    public function __construct(private readonly ?array $rawPosition = null)
    {
    }

    #[Sinful(NullToOptionalMap::class)]
    #[Sinful(PreferOptionalCreate::class)]
    public function position(): OptCoords|Optional
    {
        return $this->rawPosition === null ? new Optional : OptCoords::from($this->rawPosition);
    }
}
