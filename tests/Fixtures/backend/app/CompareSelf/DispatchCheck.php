<?php

namespace Shop\CompareSelf;

use JesseGall\CodeCommandments\Sins\Backend\CompareSelf;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

enum Dispatch
{
    case Draft;
    case Pending;
    case Sent;

    public function notEqualsAny(self ...$other): bool
    {
        return ! in_array($this, $other, true);
    }
}

#[Sinful(CompareSelf::class)]
function hasDispatched(Dispatch $dispatch): bool
{
    return $dispatch !== Dispatch::Draft && $dispatch !== Dispatch::Pending;
}

#[Fixed(CompareSelf::class)]
function alreadyDispatched(Dispatch $dispatch): bool
{
    return $dispatch->notEqualsAny(Dispatch::Draft, Dispatch::Pending);
}

#[Righteous(CompareSelf::class)]
function rank(Dispatch $dispatch): int
{
    if ($dispatch === Dispatch::Pending) {
        return 1;
    }

    return match ($dispatch) {
        Dispatch::Draft => 0,
        Dispatch::Sent => 2,
    };
}
