<?php

namespace Shop\Support;

final class ValueBag
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(private array $attributes = []) {}

    public function string(string $key): string
    {
        return (string) ($this->attributes[$key] ?? '');
    }

    public function integer(string $key): int
    {
        return (int) ($this->attributes[$key] ?? 0);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }
}
