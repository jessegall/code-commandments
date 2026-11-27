<?php

namespace Shop\Recasts;

use JesseGall\CodeCommandments\Testing\Sinful;

final class LabelPrinter
{
    #[Sinful(RecastResult::class)]
    public function print(ShelfTag $tag): string
    {
        return sprintf('[%s]', (string) $tag->code());
    }

    public function margin(int $columns, bool $wide): int
    {
        $base = $wide ? 12 : 4;
        $left = $columns;
        while ($left > 80) {
            $left -= 20;
            $base++;
        }

        return $base;
    }
}
