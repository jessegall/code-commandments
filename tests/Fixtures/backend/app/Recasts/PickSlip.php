<?php

namespace Shop\Recasts;

use JesseGall\CodeCommandments\Testing\Sinful;

final class PickSlip
{
    /**
     * @param  list<ShelfTag>  $tags
     * @return list<string>
     */
    #[Sinful(RecastResult::class)]
    public function lines(array $tags): array
    {
        $lines = [];
        foreach ($tags as $position => $tag) {
            $lines[] = $position + 1 . '. ' . (string) $tag->code();
        }

        return $lines;
    }
}
