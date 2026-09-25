<?php

namespace Toy;

use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;
use Toy\Sins\LoopedNew;

final class Cart
{
    #[Sinful(LoopedNew::class)]
    public function fill(array $rows): array
    {
        $items = [];

        foreach ($rows as $row) {
            $items[] = new Item($row['sku'], $row['quantity']);
        }

        return $items;
    }

    #[Righteous(LoopedNew::class)]
    public function one(array $row): Item
    {
        return new Item($row['sku'], $row['quantity']);
    }

    #[Fixed(LoopedNew::class)]
    public function fillAtOnce(array $rows): array
    {
        return Item::many($rows);
    }
}
