<?php

namespace Shop\Domain\Factories;

use JesseGall\CodeCommandments\Sins\Backend\ManufacturedFakeFill;
use JesseGall\CodeCommandments\Testing\Sinful;

final class ShelfCode
{
    private function __construct(private readonly string $aisle, private readonly int $slot)
    {
    }

    #[Sinful(ManufacturedFakeFill::class)]
    public static function parse(string $written): self
    {
        $parts = explode('-', $written);

        return new self($parts[0], (int) ($parts[1] ?? 0));
    }

    public function aisle(): string
    {
        return $this->aisle;
    }

    public function slot(): int
    {
        return $this->slot;
    }
}
