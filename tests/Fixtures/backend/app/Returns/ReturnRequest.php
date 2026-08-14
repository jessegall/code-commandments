<?php

namespace Shop\Returns;

final class ReturnRequest
{
    /**
     * @param  list<string>  $items
     */
    public function __construct(
        private readonly string $reference,
        private readonly string $reason,
        private readonly array $items,
    ) {}

    public function reference(): string
    {
        return $this->reference;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function itemCount(): int
    {
        return count($this->items);
    }
}
