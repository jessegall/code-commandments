<?php

function describe(array $items, int $count, string $name, object $thing): string
{
    $first = (int) $items[0];
    $shown = ! $first ? -$count : +$count;
    $bits = ~$count;
    $counted = $thing instanceof Countable;
    $label = "Item {$name} of $count";
    $all = [...$items, 3];
    $made = new $name();

    return $label;
}

function total(int $count): int
{
    return $count;
}
