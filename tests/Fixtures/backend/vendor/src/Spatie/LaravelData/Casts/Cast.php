<?php

namespace Spatie\LaravelData\Casts;

interface Cast
{
    public function cast(\Spatie\LaravelData\Support\DataProperty $property, mixed $value, array $properties, \Spatie\LaravelData\Support\Creation\CreationContext $context): mixed;
}
