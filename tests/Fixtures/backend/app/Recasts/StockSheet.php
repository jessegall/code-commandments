<?php

namespace Shop\Recasts;

use JesseGall\CodeCommandments\Testing\Sinful;

final class StockSheet
{
    /**
     * @return array<string, string|int>
     */
    #[Sinful(RecastResult::class)]
    public static function row(ShelfTag $tag, int $counted): array
    {
        return [
            'code' => strtoupper((string) $tag->code()),
            'counted' => $counted,
        ];
    }

    /**
     * @param  array<string, int>  $counts
     */
    public static function total(array $counts): int
    {
        return array_sum(array_filter($counts, static fn (int $count) => $count > 0));
    }
}
