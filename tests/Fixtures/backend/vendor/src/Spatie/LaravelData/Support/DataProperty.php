<?php

namespace Spatie\LaravelData\Support;

class DataProperty
{
    public readonly string $name;
    public readonly string $className;
    public readonly \Spatie\LaravelData\Support\DataPropertyType $type;
    public readonly bool $validate;
    public readonly bool $computed;
    public readonly bool $hidden;
    public readonly bool $isPromoted;
    public readonly bool $isReadonly;
    public readonly bool $isVirtual;
    public readonly bool $morphable;
    public readonly ?\Spatie\LaravelData\Attributes\AutoLazy $autoLazy;
    public readonly bool $hasDefaultValue;
    public readonly mixed $defaultValue;
    public readonly ?\Spatie\LaravelData\Casts\Cast $cast;
    public readonly ?\Spatie\LaravelData\Transformers\Transformer $transformer;
    public readonly ?string $inputMappedName;
    public readonly ?string $outputMappedName;
    public readonly \Spatie\LaravelData\Support\DataAttributesCollection $attributes;
    public function __construct(string $name, string $className, \Spatie\LaravelData\Support\DataPropertyType $type, bool $validate, bool $computed, bool $hidden, bool $isPromoted, bool $isReadonly, bool $isVirtual, bool $morphable, ?\Spatie\LaravelData\Attributes\AutoLazy $autoLazy, bool $hasDefaultValue, mixed $defaultValue, ?\Spatie\LaravelData\Casts\Cast $cast, ?\Spatie\LaravelData\Transformers\Transformer $transformer, ?string $inputMappedName, ?string $outputMappedName, \Spatie\LaravelData\Support\DataAttributesCollection $attributes) {}
}
