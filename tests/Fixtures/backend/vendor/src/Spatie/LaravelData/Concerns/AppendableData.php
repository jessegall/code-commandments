<?php

namespace Spatie\LaravelData\Concerns;

trait AppendableData
{
    protected array $_additional = array (
);
    public function with(): array {}
    public function additional(array $additional): static {}
    public function getAdditionalData(): array {}
}
