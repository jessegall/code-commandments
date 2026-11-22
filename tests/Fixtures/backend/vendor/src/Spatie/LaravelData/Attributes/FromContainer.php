<?php

namespace Spatie\LaravelData\Attributes;

#[\Attribute(8)]
class FromContainer implements \Spatie\LaravelData\Attributes\InjectsPropertyValue
{
    public ?string $dependency;
    public array $parameters;
    public bool $replaceWhenPresentInPayload;
    public function __construct(?string $dependency = NULL, array $parameters = array (
), bool $replaceWhenPresentInPayload = true) {}
    public function resolve(\Spatie\LaravelData\Support\DataProperty $dataProperty, mixed $payload, array $properties, \Spatie\LaravelData\Support\Creation\CreationContext $creationContext): mixed {}
    public function shouldBeReplacedWhenPresentInPayload(): bool {}
}
