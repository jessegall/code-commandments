<?php

namespace Spatie\LaravelData\Casts;

class EnumCast implements \Spatie\LaravelData\Casts\Cast, \Spatie\LaravelData\Casts\IterableItemCast
{
    protected ?string $type;
    public function __construct(?string $type = NULL) {}
    public function cast(\Spatie\LaravelData\Support\DataProperty $property, mixed $value, array $properties, \Spatie\LaravelData\Support\Creation\CreationContext $context): \BackedEnum|\Spatie\LaravelData\Casts\Uncastable {}
    public function castIterableItem(\Spatie\LaravelData\Support\DataProperty $property, mixed $value, array $properties, \Spatie\LaravelData\Support\Creation\CreationContext $context): \BackedEnum|\Spatie\LaravelData\Casts\Uncastable {}
    protected function castValue(?string $type, mixed $value, \Spatie\LaravelData\Support\DataProperty $property): \BackedEnum|\Spatie\LaravelData\Casts\Uncastable {}
}
