<?php

namespace Spatie\LaravelData\Casts;

interface IterableItemCast
{
    public function castIterableItem(\Spatie\LaravelData\Support\DataProperty $property, mixed $value, array $properties, \Spatie\LaravelData\Support\Creation\CreationContext $context): mixed;
}
