<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Classifiers;

use Spatie\LaravelData\Lazy;
use Spatie\LaravelData\Optional;

/**
 * Classifies Spatie laravel-data deferred-value markers (`Lazy`, `Optional`) —
 * these ALREADY model presence/absence, so a nullable one is not a null-object
 * candidate. Matched by short name, so an aliased or bare reference still counts.
 */
final class SpatieOptionalClassifier extends TypeClassifier
{
    protected function types(): array
    {
        return [Lazy::class, Optional::class];
    }
}
