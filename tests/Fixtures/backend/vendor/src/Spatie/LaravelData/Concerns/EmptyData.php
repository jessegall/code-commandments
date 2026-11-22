<?php

namespace Spatie\LaravelData\Concerns;

trait EmptyData
{
    public static function empty(array $extra = array (
), mixed $replaceNullValuesWith = NULL, ?array $except = NULL, ?array $only = NULL): array {}
}
