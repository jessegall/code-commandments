<?php

namespace Spatie\LaravelData\Attributes;

interface GetsCast
{
    public function get(): \Spatie\LaravelData\Casts\Cast;
}
