<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\HookMissingComputed;
use JesseGall\CodeCommandments\Testing\Righteous;
use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Data;

/*
 * Righteous twin for HookMissingComputed — the get-only hook IS marked `#[Computed]`, so Spatie treats it
 * as an output-only value, not a hydration input. Must NOT flag.
 */
#[Righteous(HookMissingComputed::class)]
final class TagCloud extends Data
{
    #[Computed]
    public array $tags { get => array_keys($this->counts); }

    /**
     * @param array<string, int> $counts
     */
    public function __construct(public readonly array $counts) {}
}
