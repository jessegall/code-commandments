<?php

namespace Shop;

use Attribute;

#[Attribute]
final class Marked {}

final class Limits
{
    #[Marked]
    public const int MAX = 3;

    public function max(): int
    {
        return self::MAX;
    }
}

enum Suit: string
{
    #[Marked]
    case Hearts = 'H';
    case Spades = 'S';

    public function color(): string
    {
        // @contract kind/dispatch
        // @contract fact/dispatch/form = "match"
        // @contract fact/dispatch/exhaustive = true
        return match ($this) {
            Suit::Hearts => 'red',
            Suit::Spades => 'black',
        };
    }
}

function run(): void
{
    ;
    echo 1;
}
