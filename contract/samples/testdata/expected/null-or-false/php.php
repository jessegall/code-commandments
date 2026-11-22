<?php

function label(?string $name, string $other): string
{
    // @contract kind/fallback
    // @contract fact/fallback/trigger = "null"
    $first = $name ?? $other;
    $second = $name ?: $other;
    // @contract kind/logical
    // @contract fact/logical/operator = "or"
    $both = $name || $other;

    return $first . $second;
}
