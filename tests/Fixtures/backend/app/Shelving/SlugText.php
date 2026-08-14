<?php

namespace Shop\Shelving;

final class SlugText
{
    public static function of(string $name): string
    {
        return strtolower(str_replace(' ', '-', $name));
    }
}
