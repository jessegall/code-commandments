<?php

namespace Spatie\LaravelData\Attributes;

interface InjectsPropertyValue
{
    public function resolve(\Spatie\LaravelData\Support\DataProperty $dataProperty, mixed $payload, array $properties, \Spatie\LaravelData\Support\Creation\CreationContext $creationContext): mixed;
    public function shouldBeReplacedWhenPresentInPayload(): bool;
}
