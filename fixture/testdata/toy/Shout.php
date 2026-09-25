<?php

namespace Toy;

use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;
use Toy\Sins\Shouting;

final class Shout
{
    #[Sinful(Shouting::class)]
    public function greet(string $name): string
    {
        return strtoupper("hello {$name}");
    }

    #[Righteous(Shouting::class)]
    public function whisper(string $name): string
    {
        return strtolower("hello {$name}");
    }
}
