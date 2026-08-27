<?php

namespace Shop\Support;

use JesseGall\CodeCommandments\Sins\Backend\NullableRegistryLookup;
use JesseGall\CodeCommandments\Testing\Fixed;
use RuntimeException;

/**
 * The failure the nullable lookup used to hand back as null. Naming it is what lets the store
 * resolve-or-throw, so no caller re-decides what a missing channel means.
 */
#[Fixed(NullableRegistryLookup::class)]
final class UnknownChannel extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self("No notification channel is registered under {$key}.");
    }
}
