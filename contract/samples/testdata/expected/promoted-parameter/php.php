<?php

namespace App;

final class Money
{
    // @contract kind/parameter
    // @contract fact/*/modifiers on parameter = ["private","readonly"]
    public function __construct(private readonly int $amount)
    {
    }
}
