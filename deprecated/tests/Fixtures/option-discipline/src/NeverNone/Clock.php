<?php
namespace Acme\Notify\NeverNone;
use JesseGall\PhpTypes\Option;

final class Clock
{
    // CASE B: typed `: Option` but every return is some() — never empty.
    public function now(): Option
    {
        return Option::some(new \DateTimeImmutable());
    }
}
