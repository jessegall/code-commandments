<?php

namespace Spatie\LaravelData\Support\Partials;

class PartialsCollection extends \SplObjectStorage implements \Stringable
{
    public static function create(\Spatie\LaravelData\Support\Partials\Partial ...$partials): \Spatie\LaravelData\Support\Partials\PartialsCollection {}
    public function toArray(): array {}
    public function toSerializedArray(): array {}
    public function __toString(): string {}
    public static function fromSerializedArray(array $collection): \Spatie\LaravelData\Support\Partials\PartialsCollection {}
    public function manualClone(): \Spatie\LaravelData\Support\Partials\PartialsCollection {}
}
