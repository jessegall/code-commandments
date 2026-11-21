<?php

function label(?string $name, string $other): string
{
    $first = $name ?? $other;
    $second = $name ?: $other;
    $both = $name || $other;

    return $first . $second;
}
