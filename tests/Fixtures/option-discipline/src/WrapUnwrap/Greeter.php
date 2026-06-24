<?php
namespace Acme\Notify\WrapUnwrap;
use JesseGall\PhpTypes\Option;

final class Greeter
{
    // CASE D: an Option built and unwrapped in one breath.
    public function greet(string $name): string
    {
        return Option::some($name)->unwrap();
    }
}
