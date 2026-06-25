<?php

declare(strict_types=1);

namespace Shop\Cart;

interface Discount
{
    public function applyTo(int $amountCents): int;

    public function label(): string;
}
