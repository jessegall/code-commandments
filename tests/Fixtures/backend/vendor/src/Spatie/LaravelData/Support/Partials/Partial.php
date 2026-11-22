<?php

namespace Spatie\LaravelData\Support\Partials;

class Partial implements \Stringable
{
    public int $segmentCount;
    public bool $endsInAll;
    public array $segments;
    public bool $permanent;
    public ?\Closure $condition;
    public int $pointer;
    public function __construct(array $segments, bool $permanent = false, ?\Closure $condition = NULL, int $pointer = 0) {}
    public static function create(string $path, bool $permanent = false): \Spatie\LaravelData\Support\Partials\Partial {}
    public static function createConditional(string $path, \Closure $condition, bool $permanent = false): \Spatie\LaravelData\Support\Partials\Partial {}
    public static function fromMethodDefinedKeyAndValue(string|int $key, callable|string|bool $value) {}
    protected static function resolveSegmentsFromPath(string $path): array {}
    public function isUndefined(): bool {}
    public function isAll(): bool {}
    public function getNested(): ?string {}
    public function getFields(): ?array {}
    public function toLaravel(): array {}
    public function next(): \Spatie\LaravelData\Support\Partials\Partial {}
    public function rollbackWhenRequired(): void {}
    public function reset(): \Spatie\LaravelData\Support\Partials\Partial {}
    protected function getCurrentSegment(): ?\Spatie\LaravelData\Support\Partials\Segments\PartialSegment {}
    public function isRequired(\Spatie\LaravelData\Contracts\IncludeableData $data): bool {}
    public function toArray(): array {}
    public function toSerializedArray(): array {}
    public static function fromSerializedArray(array $partial): \Spatie\LaravelData\Support\Partials\Partial {}
    public function __toString(): string {}
    public function __serialize(): array {}
    public function __unserialize(array $data): void {}
}
