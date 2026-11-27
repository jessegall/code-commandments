<?php

namespace Shop\Recasts;

use JesseGall\CodeCommandments\Testing\Righteous;

final class WeighScale
{
    #[Righteous(RecastResult::class)]
    public function grams(ShelfTag $tag): int
    {
        return (int) $tag->weight();
    }

    public function shown(ShelfTag $tag): string
    {
        return 'weight ' . $tag->weight();
    }

    public function isHeavy(ShelfTag $tag): bool
    {
        return $tag->weight() > 1000;
    }
}
