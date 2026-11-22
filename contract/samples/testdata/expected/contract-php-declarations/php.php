<?php

namespace Shop;

use Attribute;

#[Attribute]
final class Tag
{
    public function __construct(public readonly string $name = 'none') {}
}

interface Named
{
    public function name(): string;
}

trait Greets
{
    public function greet(): string
    {
        return 'hi';
    }
}

#[Tag('shop')]
final class Item implements Named
{
    use Greets;

    #[Tag('size')]
    public int $size = 0;

    public const int LIMIT = 3;

    #[Tag('name')]
    public function name(#[Tag('label')] string|int $label = 'x'): string
    {
        return $this->size > self::LIMIT ? 'big' : 'none';
    }
}
