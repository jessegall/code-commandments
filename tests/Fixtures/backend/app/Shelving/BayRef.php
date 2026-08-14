<?php

namespace Shop\Shelving;

final class BayRef
{
    private function __construct(private readonly string $code) {}

    public static function from(string $raw): self
    {
        return new self(trim($raw));
    }

    public function label(): string
    {
        return 'Bay ' . $this->code;
    }
}
