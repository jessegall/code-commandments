<?php

namespace Spatie\LaravelData\Support;

class DataType
{
    public readonly \Spatie\LaravelData\Support\Types\Type $type;
    public readonly bool $isNullable;
    public readonly bool $isMixed;
    public readonly \Spatie\LaravelData\Enums\DataTypeKind $kind;
    public function __construct(\Spatie\LaravelData\Support\Types\Type $type, bool $isNullable, bool $isMixed, \Spatie\LaravelData\Enums\DataTypeKind $kind) {}
    public function findAcceptedTypeForBaseType(string $class): ?string {}
    public function acceptsType(string $type): bool {}
    public function getAcceptedTypes(): array {}
    public function acceptsValue(mixed $value): bool {}
}
