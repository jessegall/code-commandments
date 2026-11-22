<?php

namespace Spatie\LaravelData\Support;

class DataPropertyType extends \Spatie\LaravelData\Support\DataType
{
    public readonly bool $isOptional;
    public readonly ?string $lazyType;
    public readonly ?string $dataClass;
    public readonly ?string $dataCollectableClass;
    public readonly ?string $iterableClass;
    public readonly ?string $iterableItemType;
    public readonly ?string $iterableKeyType;
    public function __construct(\Spatie\LaravelData\Support\Types\Type $type, bool $isOptional, bool $isNullable, bool $isMixed, ?string $lazyType, \Spatie\LaravelData\Enums\DataTypeKind $kind, ?string $dataClass, ?string $dataCollectableClass, ?string $iterableClass, ?string $iterableItemType, ?string $iterableKeyType) {}
}
