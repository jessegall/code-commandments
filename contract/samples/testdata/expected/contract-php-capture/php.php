<?php

function priced(array $prices, ?int $floor): array
{
    // @contract fact/function/capture = "value"
    $raised = static fn (int $price): int => max($price, $floor ?? 0);

    // @contract fact/call/form = "presence"
    if (isset($prices[0])) {
        return array_map($raised, $prices);
    }

    return [];
}
