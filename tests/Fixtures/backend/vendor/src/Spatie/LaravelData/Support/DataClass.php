<?php

namespace Spatie\LaravelData\Support;

class DataClass
{
    public readonly string $name;
    public readonly \Illuminate\Support\Collection $properties;
    public readonly \Illuminate\Support\Collection $methods;
    public readonly ?\Spatie\LaravelData\Support\DataMethod $constructorMethod;
    public readonly bool $isReadonly;
    public readonly bool $isAbstract;
    public readonly bool $propertyMorphable;
    public readonly bool $appendable;
    public readonly bool $includeable;
    public readonly bool $responsable;
    public readonly bool $transformable;
    public readonly bool $validateable;
    public readonly bool $wrappable;
    public readonly bool $emptyData;
    public readonly bool $hasDynamicValidationRules;
    public readonly \Spatie\LaravelData\Support\DataAttributesCollection $attributes;
    public readonly array $dataIterablePropertyAnnotations;
    public \Spatie\LaravelData\Support\DataStructureProperty $allowedRequestIncludes;
    public \Spatie\LaravelData\Support\DataStructureProperty $allowedRequestExcludes;
    public \Spatie\LaravelData\Support\DataStructureProperty $allowedRequestOnly;
    public \Spatie\LaravelData\Support\DataStructureProperty $allowedRequestExcept;
    public \Spatie\LaravelData\Support\DataStructureProperty $outputMappedProperties;
    public \Spatie\LaravelData\Support\DataStructureProperty $transformationFields;
    public function __construct(string $name, \Illuminate\Support\Collection $properties, \Illuminate\Support\Collection $methods, ?\Spatie\LaravelData\Support\DataMethod $constructorMethod, bool $isReadonly, bool $isAbstract, bool $propertyMorphable, bool $appendable, bool $includeable, bool $responsable, bool $transformable, bool $validateable, bool $wrappable, bool $emptyData, bool $hasDynamicValidationRules, \Spatie\LaravelData\Support\DataAttributesCollection $attributes, array $dataIterablePropertyAnnotations, \Spatie\LaravelData\Support\DataStructureProperty $allowedRequestIncludes, \Spatie\LaravelData\Support\DataStructureProperty $allowedRequestExcludes, \Spatie\LaravelData\Support\DataStructureProperty $allowedRequestOnly, \Spatie\LaravelData\Support\DataStructureProperty $allowedRequestExcept, \Spatie\LaravelData\Support\DataStructureProperty $outputMappedProperties, \Spatie\LaravelData\Support\DataStructureProperty $transformationFields) {}
    public function prepareForCache(): void {}
}
