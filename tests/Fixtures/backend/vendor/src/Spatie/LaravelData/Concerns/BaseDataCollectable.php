<?php

namespace Spatie\LaravelData\Concerns;

trait BaseDataCollectable
{
    protected ?\Spatie\LaravelData\Support\Transformation\DataContext $_dataContext = NULL;
    public function getDataClass(): string {}
    public function getIterator(): \ArrayIterator {}
    public function count(): int {}
    public function __sleep(): array {}
}
