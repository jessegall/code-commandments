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
    // @label firstMember type-expression 42 inner
    // @label secondMember type-expression 49
    // @contract role/type-expression/members at 42 -> [firstMember, secondMember]
    public function name(#[Tag('label')] string|int $label = 'x'): string
    {
        // @contract fact/*/constantValue on access at 30 = 3
        return $this->size > self::LIMIT ? 'big' : 'none';
    }
}
