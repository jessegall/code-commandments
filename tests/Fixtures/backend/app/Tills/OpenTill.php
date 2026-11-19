<?php

namespace Shop\Tills;

use Illuminate\Contracts\Auth\Guard;

final class OpenTill
{
    public function __construct(private Guard $guard) {}

    public function number(): int
    {
        return (int) $this->guard->id();
    }
}
