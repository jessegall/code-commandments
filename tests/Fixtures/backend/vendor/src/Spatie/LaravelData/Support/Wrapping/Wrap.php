<?php

namespace Spatie\LaravelData\Support\Wrapping;

class Wrap
{
    public \Spatie\LaravelData\Support\Wrapping\WrapType $type;
    public ?string $key;
    public function __construct(\Spatie\LaravelData\Support\Wrapping\WrapType $type, ?string $key = NULL) {}
    public function wrap(array $data): array {}
    public function getKey(): ?string {}
    public function toSerializedArray(): array {}
    public static function fromSerializedArray(array $wrap): \Spatie\LaravelData\Support\Wrapping\Wrap {}
}
