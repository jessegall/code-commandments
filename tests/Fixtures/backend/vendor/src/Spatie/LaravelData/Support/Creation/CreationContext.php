<?php

namespace Spatie\LaravelData\Support\Creation;

class CreationContext
{
    public string $dataClass;
    public array $mappedProperties;
    public array $currentPath;
    public \Spatie\LaravelData\Support\Creation\ValidationStrategy $validationStrategy;
    public readonly bool $mapPropertyNames;
    public readonly bool $disableMagicalCreation;
    public readonly bool $useOptionalValues;
    public readonly ?array $ignoredMagicalMethods;
    public readonly ?\Spatie\LaravelData\Support\Creation\GlobalCastsCollection $casts;
    public function __construct(string $dataClass, array $mappedProperties, array $currentPath, \Spatie\LaravelData\Support\Creation\ValidationStrategy $validationStrategy, bool $mapPropertyNames, bool $disableMagicalCreation, bool $useOptionalValues, ?array $ignoredMagicalMethods, ?\Spatie\LaravelData\Support\Creation\GlobalCastsCollection $casts) {}
    public function from(mixed ...$payloads): \Spatie\LaravelData\Contracts\BaseData {}
    public function collect(mixed $items, ?string $into = NULL): \Spatie\LaravelData\DataCollection|\Spatie\LaravelData\PaginatedDataCollection|\Spatie\LaravelData\CursorPaginatedDataCollection|\Illuminate\Support\Enumerable|\Illuminate\Pagination\AbstractPaginator|\Illuminate\Contracts\Pagination\Paginator|\Illuminate\Pagination\AbstractCursorPaginator|\Illuminate\Contracts\Pagination\CursorPaginator|\Illuminate\Support\LazyCollection|\Illuminate\Support\Collection|array {}
    public function next(string $dataClass, string|int $path): \Spatie\LaravelData\Support\Creation\CreationContext {}
    public function previous(): \Spatie\LaravelData\Support\Creation\CreationContext {}
}
