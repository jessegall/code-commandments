<?php

namespace Spatie\LaravelData\Support\Creation;

class CreationContextFactory
{
    public string $dataClass;
    public \Spatie\LaravelData\Support\Creation\ValidationStrategy $validationStrategy;
    public bool $mapPropertyNames;
    public bool $disableMagicalCreation;
    public bool $useOptionalValues;
    public ?array $ignoredMagicalMethods;
    public ?\Spatie\LaravelData\Support\Creation\GlobalCastsCollection $casts;
    public function __construct(string $dataClass, \Spatie\LaravelData\Support\Creation\ValidationStrategy $validationStrategy, bool $mapPropertyNames, bool $disableMagicalCreation, bool $useOptionalValues, ?array $ignoredMagicalMethods, ?\Spatie\LaravelData\Support\Creation\GlobalCastsCollection $casts) {}
    public static function createFromConfig(string $dataClass, ?array $config = NULL): \Spatie\LaravelData\Support\Creation\CreationContextFactory {}
    public static function createFromCreationContext(string $dataClass, \Spatie\LaravelData\Support\Creation\CreationContext $creationContext): \Spatie\LaravelData\Support\Creation\CreationContextFactory {}
    public function validationStrategy(\Spatie\LaravelData\Support\Creation\ValidationStrategy $validationStrategy): \Spatie\LaravelData\Support\Creation\CreationContextFactory {}
    public function withoutValidation(): \Spatie\LaravelData\Support\Creation\CreationContextFactory {}
    public function onlyValidateRequests(): \Spatie\LaravelData\Support\Creation\CreationContextFactory {}
    public function alwaysValidate(): \Spatie\LaravelData\Support\Creation\CreationContextFactory {}
    public function withPropertyNameMapping(bool $withPropertyNameMapping = true): \Spatie\LaravelData\Support\Creation\CreationContextFactory {}
    public function withoutPropertyNameMapping(bool $withoutPropertyNameMapping = true): \Spatie\LaravelData\Support\Creation\CreationContextFactory {}
    public function withoutMagicalCreation(bool $withoutMagicalCreation = true): \Spatie\LaravelData\Support\Creation\CreationContextFactory {}
    public function withMagicalCreation(bool $withMagicalCreation = true): \Spatie\LaravelData\Support\Creation\CreationContextFactory {}
    public function withOptionalValues(bool $withOptionalValues = true): \Spatie\LaravelData\Support\Creation\CreationContextFactory {}
    public function withoutOptionalValues(bool $withoutOptionalValues = true): \Spatie\LaravelData\Support\Creation\CreationContextFactory {}
    public function ignoreMagicalMethod(string ...$methods): \Spatie\LaravelData\Support\Creation\CreationContextFactory {}
    public function withCast(string $castable, \Spatie\LaravelData\Casts\Cast|string $cast): \Spatie\LaravelData\Support\Creation\CreationContextFactory {}
    public function withCastCollection(\Spatie\LaravelData\Support\Creation\GlobalCastsCollection $casts): \Spatie\LaravelData\Support\Creation\CreationContextFactory {}
    public function get(): \Spatie\LaravelData\Support\Creation\CreationContext {}
    /** @return TData */
    public function from(mixed ...$payloads) {}
    public function collect(mixed $items, ?string $into = NULL): \Spatie\LaravelData\DataCollection|\Spatie\LaravelData\PaginatedDataCollection|\Spatie\LaravelData\CursorPaginatedDataCollection|\Illuminate\Support\Enumerable|\Illuminate\Pagination\AbstractPaginator|\Illuminate\Contracts\Pagination\Paginator|\Illuminate\Pagination\AbstractCursorPaginator|\Illuminate\Contracts\Pagination\CursorPaginator|\Illuminate\Support\LazyCollection|\Illuminate\Support\Collection|array {}
}
