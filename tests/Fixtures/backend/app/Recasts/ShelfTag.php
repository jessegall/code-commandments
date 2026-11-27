<?php

namespace Shop\Recasts;

final readonly class ShelfTag
{
    public function __construct(private ?string $code, private int|string $weight) {}

    public function code(): ?string
    {
        return $this->code;
    }

    public function weight(): int|string
    {
        return $this->weight;
    }
}
