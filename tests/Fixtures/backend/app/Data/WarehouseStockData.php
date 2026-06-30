<?php

namespace Shop\Data;

use JesseGall\CodeCommandments\Sins\Backend\DataMethodHintCollision;

use Illuminate\Support\Collection;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;

/**
 * Stock-on-hand per warehouse. The conditional-return `@method` collides with the
 * real `collect()` override below — the parens in the return type must not hide it.
 *
 * @method static ($items is \Illuminate\Support\Collection ? \Illuminate\Support\Collection<int, static> : array<int, static>) collect(iterable $items)
 */
#[Sinful(DataMethodHintCollision::class)]
final class WarehouseStockData extends Data
{
    public function __construct(
        public readonly string $sku,
        public readonly int $onHand,
    ) {}

    public static function collect(iterable $items, ?string $into = null): array|Collection
    {
        return is_array($items) ? (array) $items : Collection::make($items);
    }
}
