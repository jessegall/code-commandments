<?php

namespace Shop\Shelving;

use JesseGall\CodeCommandments\Sins\Backend\BlankStringDefault;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * The handle a bay answers to on the picking screen. The field starts blank and `handle()` reads that
 * blank back as "nobody named it", falling through to the slug — the absence lives in the value, where
 * the type says a handle is always there.
 */
#[Sinful(BlankStringDefault::class)]
final class BayHandle
{
    private string $handle = '';

    public function __construct(
        private readonly string $slug,
        private readonly int $aisle,
    ) {}

    public function named(string $handle): static
    {
        $this->handle = $handle;

        return $this;
    }

    public function handle(): string
    {
        return $this->handle !== '' ? $this->handle : $this->slug;
    }

    public function aisle(): int
    {
        return $this->aisle;
    }
}
