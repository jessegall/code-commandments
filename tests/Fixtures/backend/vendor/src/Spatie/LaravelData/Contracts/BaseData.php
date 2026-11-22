<?php

namespace Spatie\LaravelData\Contracts;

interface BaseData
{
    public static function optional(mixed ...$payloads): ?static;
    public static function from(mixed ...$payloads): static;
    public static function collect(mixed $items, ?string $into = NULL): \Spatie\LaravelData\DataCollection|\Spatie\LaravelData\PaginatedDataCollection|\Spatie\LaravelData\CursorPaginatedDataCollection|\Illuminate\Support\Enumerable|\Illuminate\Pagination\AbstractPaginator|\Illuminate\Contracts\Pagination\Paginator|\Illuminate\Pagination\AbstractCursorPaginator|\Illuminate\Contracts\Pagination\CursorPaginator|\Illuminate\Support\LazyCollection|\Illuminate\Support\Collection|array;
    public static function factory(?\Spatie\LaravelData\Support\Creation\CreationContext $creationContext = NULL): \Spatie\LaravelData\Support\Creation\CreationContextFactory;
    public static function normalizers(): array;
    public static function prepareForPipeline(array $properties): array;
    public static function pipeline(): \Spatie\LaravelData\DataPipeline;
}
