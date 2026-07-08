<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes;

use JesseGall\CodeCommandments\Support\ClassName;

/**
 * The `--only` selector name of a scribe — its class basename. Shared by the two scribe roots
 * ({@see Scribe}, {@see RepentScribe}), which are separate hierarchies, so the one computation lives here
 * instead of once in each base.
 */
trait NamedByClass
{
    public function name(): string
    {
        return ClassName::short(static::class);
    }
}
