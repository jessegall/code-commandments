<?php

namespace Spatie\LaravelData\Attributes;

#[\Attribute(9)]
class WithTransformer
{
    public array $arguments;
    public string $transformerClass;
    public function __construct(string $transformerClass, mixed ...$arguments) {}
    public function get(): \Spatie\LaravelData\Transformers\Transformer {}
}
