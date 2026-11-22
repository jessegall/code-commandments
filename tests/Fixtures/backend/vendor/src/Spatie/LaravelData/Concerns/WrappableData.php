<?php

namespace Spatie\LaravelData\Concerns;

trait WrappableData
{
    public function withoutWrapping(): static {}
    public function wrap(string $key): static {}
    public function getWrap(): \Spatie\LaravelData\Support\Wrapping\Wrap {}
}
