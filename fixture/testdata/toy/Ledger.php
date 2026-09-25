<?php

namespace Toy;

use JesseGall\CodeCommandments\Testing\Sinful;
use Toy\Sins\LoopedNew;

#[Sinful(LoopedNew::class)]
final class Ledger
{
    private array $entries = [];

    public function replay(\Iterator $events): void
    {
        while ($events->valid()) {
            $this->entries[] = new Entry(
                amount: $events->current()->amount,
                at: $events->key(),
            );
            $events->next();
        }
    }
}
