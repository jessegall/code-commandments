<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\AllOptionalData;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/*
 * Scenario 1 — a layout box where every dimension is Optional. A grid with no columns is not a grid; the
 * optionality belongs on the parent's `GridBox|Optional $grid`, and these leaves want concrete defaults.
 */
#[Sinful(AllOptionalData::class)]
final class GridBox extends Data
{
    public function __construct(
        public readonly int|Optional $columns = new Optional(),
        public readonly int|Optional $span = new Optional(),
        public readonly int|Optional $gap = new Optional(),
    ) {}

    public function template(): string
    {
        $cols = $this->columns instanceof Optional ? 1 : $this->columns;
        $gap = $this->gap instanceof Optional ? 0 : $this->gap;

        return "grid-template-columns: repeat({$cols}, 1fr); gap: {$gap}px";
    }

    public function area(int $rows): int
    {
        $cols = $this->columns instanceof Optional ? 1 : $this->columns;

        return $rows * $cols;
    }

    public function fitsWithin(int $trackCount): bool
    {
        if ($this->span instanceof Optional) {
            return true;
        }

        return $this->span <= $trackCount;
    }
}
