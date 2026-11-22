<?php

namespace Spatie\LaravelData\Contracts;

interface ResponsableData extends \Illuminate\Contracts\Support\Responsable
{
    public function toResponse($request);
    public static function allowedRequestIncludes(): ?array;
    public static function allowedRequestExcludes(): ?array;
    public static function allowedRequestOnly(): ?array;
    public static function allowedRequestExcept(): ?array;
}
