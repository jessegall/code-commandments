<?php

namespace Spatie\LaravelData\Transformers;

class DateTimeInterfaceTransformer implements \Spatie\LaravelData\Transformers\Transformer
{
    protected string $format;
    protected ?string $setTimeZone;
    public function __construct(?string $format = NULL, ?string $setTimeZone = NULL) {}
    public function transform(\Spatie\LaravelData\Support\DataProperty $property, mixed $value, \Spatie\LaravelData\Support\Transformation\TransformationContext $context): string {}
}
