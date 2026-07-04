<?php

namespace Shop\Http\Pages;

use Spatie\LaravelData\Data;

/**
 * The small nested Data a page object composes its payload from — leaf DTOs, righteous for every
 * detector (final, readonly, honest scalar types).
 */
final class StatCard extends Data
{
    public function __construct(
        public readonly string $label,
        public readonly string $value,
    ) {}
}

final class MenuLink extends Data
{
    public function __construct(
        public readonly string $label,
        public readonly string $href,
    ) {}
}

final class CartLine extends Data
{
    public function __construct(
        public readonly string $sku,
        public readonly int $qty,
    ) {}
}
