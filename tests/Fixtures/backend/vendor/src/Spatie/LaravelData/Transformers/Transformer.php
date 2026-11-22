<?php

namespace Spatie\LaravelData\Transformers;

interface Transformer
{
    public function transform(\Spatie\LaravelData\Support\DataProperty $property, mixed $value, \Spatie\LaravelData\Support\Transformation\TransformationContext $context): mixed;
}
