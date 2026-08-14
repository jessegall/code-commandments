<?php

namespace Shop\Shelving;

use JesseGall\CodeCommandments\Sins\Backend\ConvertedArgument;

use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Reads a supplier's sheet. It slugs on the way in too — the second spelling of one rule.
 */
final class ShelfImporter
{
    public function __construct(private readonly ShelfIndex $index) {}

    #[Sinful(ConvertedArgument::class)]
    public function import(string $heading, int $bay): void
    {
        $this->index->reserve(SlugText::of($heading), $bay);
    }

    /**
     * Righteous twin: a lookup, not a reservation — `bayOf()` is read with a slug the caller already
     * holds, so nothing is being converted at its boundary.
     */
    #[Righteous(ConvertedArgument::class)]
    public function bayFor(string $slug): int
    {
        return $this->index->bayOf($slug);
    }
}
