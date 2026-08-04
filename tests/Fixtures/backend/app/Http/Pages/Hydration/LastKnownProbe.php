<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\NullToOptionalMap;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\PreferOptionalCreate;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Optional;

/*
 * A memoised probe over a tag: it remembers the last one and maps a cache miss onto `Optional` with a `??`
 * coalesce — a third distinct shape.
 */
final class LastKnownProbe
{
    private ?OptTag $memo = null;

    public function warm(OptTag $tag): void
    {
        $this->memo = $tag;
    }

    public function forget(): void
    {
        $this->memo = null;
    }

    public function isWarm(): bool
    {
        return $this->memo !== null;
    }

    #[Sinful(NullToOptionalMap::class)]
    #[Sinful(PreferOptionalCreate::class)]
    public function latest(): OptTag|Optional
    {
        return $this->memo ?? new Optional;
    }

    /**
     * The FIX: Spatie's own `Optional::create()` factory replaces the raw `new Optional` — a static
     * call is legal here, so the factory is what belongs (only a parameter/property DEFAULT, where a
     * call is illegal, keeps `new Optional`).
     */
    #[Fixed(PreferOptionalCreate::class)]
    public function lastKnown(): OptTag|Optional
    {
        if ($this->memo === null) {
            return Optional::create();
        }

        return $this->memo;
    }
}
