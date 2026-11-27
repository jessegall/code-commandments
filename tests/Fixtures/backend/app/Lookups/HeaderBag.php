<?php

namespace Shop\Lookups;

final class HeaderBag
{
    public function contains(string $name): bool
    {
        return false;
    }

    public function value(string $name): string
    {
        return $name;
    }
}
