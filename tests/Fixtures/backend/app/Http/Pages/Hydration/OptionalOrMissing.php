<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\NullToOptionalMap;
use JesseGall\CodeCommandments\Testing\Righteous;
use Spatie\LaravelData\Optional;
use JesseGall\CodeCommandments\Testing\Fixed;

/*
 * Righteous twin: the shared `optionalOrMissing()` home ITSELF — the ONE named factory the rule tells
 * producers to create (the scaffolded trait). Its whole job is the null→Optional map, so the map living
 * here is correct, not a sin. The tell that separates it from a producer is `static::from` — it hydrates
 * its OWN type from a generic payload, where a producer names a concrete OTHER type or coalesces a value.
 * Must NOT flag.
 */
#[Righteous(NullToOptionalMap::class)]
#[Fixed(NullToOptionalMap::class)]
trait OptionalOrMissing
{
    public static function optionalOrMissing(mixed $payload): static|Optional
    {
        return $payload === null ? Optional::create() : static::from($payload);
    }
}
