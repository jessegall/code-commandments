<?php

namespace Spatie\LaravelData\Contracts;

interface WrappableData
{
    public function withoutWrapping();
    public function wrap(string $key);
    public function getWrap(): \Spatie\LaravelData\Support\Wrapping\Wrap;
}
