<?php

namespace Spatie\LaravelData\Contracts;

interface IncludeableData
{
    public function include(string ...$includes): object;
    public function exclude(string ...$excludes): object;
    public function only(string ...$only): object;
    public function except(string ...$except): object;
    public function includeWhen(string $include, \Closure|bool $condition): object;
    public function excludeWhen(string $exclude, \Closure|bool $condition): object;
    public function onlyWhen(string $only, \Closure|bool $condition): object;
    public function exceptWhen(string $except, \Closure|bool $condition): object;
}
