<?php

namespace Spatie\LaravelData\Support\Transformation;

class GlobalTransformersCollection implements \IteratorAggregate, \Traversable
{
    protected array $transformers;
    public function __construct(array $transformers = array (
)) {}
    public function add(string $transformable, \Spatie\LaravelData\Transformers\Transformer $transformer): \Spatie\LaravelData\Support\Transformation\GlobalTransformersCollection {}
    public function findTransformerForValue(mixed $value): ?\Spatie\LaravelData\Transformers\Transformer {}
    public function getIterator(): \Traversable {}
}
