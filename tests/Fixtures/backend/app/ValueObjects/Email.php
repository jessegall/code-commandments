<?php

namespace Shop\ValueObjects;

final readonly class Email
{
    private function __construct(public string $value) {}

    public static function of(string $value): self
    {
        return new self(strtolower(trim($value)));
    }

    public function domain(): string
    {
        return substr($this->value, strpos($this->value, '@') + 1);
    }
}
