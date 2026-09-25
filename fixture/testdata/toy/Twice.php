<?php

namespace Toy;

use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;
use Toy\Sins\Doubled;

final class First
{
    #[Sinful(Doubled::class)]
    public function twice(): string
    {
        return 'a' . 'a';
    }

    #[Fixed(Doubled::class)]
    public function once(): string
    {
        return str_repeat('a', 2);
    }
}

final class Second
{
    #[Sinful(Doubled::class)]
    public function twice(): string
    {
        return 'b' . 'b';
    }

    #[Fixed(Doubled::class)]
    public function once(): string
    {
        return str_repeat('b', 2);
    }
}
