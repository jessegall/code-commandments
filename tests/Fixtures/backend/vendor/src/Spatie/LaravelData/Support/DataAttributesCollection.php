<?php

namespace Spatie\LaravelData\Support;

class DataAttributesCollection
{
    protected array $attributes;
    public function __construct(array $attributes = array (
)) {}
    public function has(string $type): bool {}
    public function first(string $type): ?object {}
    public function all(string $type): array {}
}
