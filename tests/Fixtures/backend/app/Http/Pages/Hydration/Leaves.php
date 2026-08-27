<?php

namespace Shop\Http\Pages\Hydration;

use Spatie\LaravelData\Data;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\DataToArrayRoundtrip;
use JesseGall\CodeCommandments\Testing\Fixed;

/*
 * Shared leaf Data classes, enums, and stubs the hydration-site fixtures nest, derive, and cast. Declared
 * once here (no findings of their own); the per-scenario site files reference them.
 */

#[Fixed(DataToArrayRoundtrip::class)]
final class BadgeCopy extends Data
{
    public function __construct(public readonly string $label, public readonly string $tone) {}
}

final class TabCopy extends Data
{
    public function __construct(public readonly string $id, public readonly string $title) {}
}

final class HeaderCopy extends Data
{
    public function __construct(public readonly string $heading) {}
}

final class ToolbarPanel extends Data
{
    public function __construct(public readonly HeaderCopy $header) {}
}

enum ShipState: string
{
    case Pending = 'pending';
    case Shipped = 'shipped';
    case Lost = 'lost';
}

final class StateChip extends Data
{
    public function __construct(public readonly string $label, public readonly string $tone) {}

    public static function for(ShipState $state): self
    {
        return new self(ucfirst($state->value), $state === ShipState::Lost ? 'danger' : 'ok');
    }

    public static function themed(ShipState $state, string $theme): self
    {
        return new self(ucfirst($state->value), $theme);
    }
}

final class ProductBadge extends Data
{
    public function __construct(public readonly string $sku, public readonly string $caption) {}

    public static function forProduct(string $sku): self
    {
        return new self($sku, "SKU {$sku}");
    }
}

final class GridTile extends Data
{
    public function __construct(public readonly int $x, public readonly int $y) {}

    public static function make(int $index): self
    {
        return new self($index % 4, intdiv($index, 4));
    }
}

enum FulfilmentState: string
{
    case Open = 'open';
    case Packed = 'packed';
    case Done = 'done';
}

/**
 * A stand-in date wrapper — short-named `Carbon`, so Spatie's built-in date cast applies.
 */
final class Carbon
{
    public static function parse(string $value): self
    {
        return new self();
    }
}
