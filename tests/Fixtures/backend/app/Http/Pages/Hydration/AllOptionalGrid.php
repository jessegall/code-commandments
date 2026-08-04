<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\AllOptionalData;
use JesseGall\CodeCommandments\Testing\Fixed;
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

/**
 * The FIX for {@see GridBox}: every leaf gets a CONCRETE default — a box always has a column count,
 * a span and a gap — and the optionality moves UP to the container field where the box itself may be
 * absent (`LayoutBox|Optional $grid = new Optional()`). Present means valid; absent is one question,
 * asked once, at the place that owns it.
 */
#[Fixed(AllOptionalData::class)]
final class LayoutBox extends Data
{
    public function __construct(
        public readonly int $columns = 1,
        public readonly int $span = 1,
        public readonly int $gap = 0,
    ) {}

    public function template(): string
    {
        return "grid-template-columns: repeat({$this->columns}, 1fr); gap: {$this->gap}px";
    }

    public function area(int $rows): int
    {
        return $rows * $this->columns;
    }
}

/**
 * The container that carries the absence the leaves used to scatter.
 */
final class LayoutPanel extends Data
{
    public function __construct(
        public readonly string $title,
        public readonly LayoutBox|Optional $grid = new Optional(),
    ) {}

    public function hasGrid(): bool
    {
        return ! $this->grid instanceof Optional;
    }
}
