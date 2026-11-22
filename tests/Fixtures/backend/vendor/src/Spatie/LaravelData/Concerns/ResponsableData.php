<?php

namespace Spatie\LaravelData\Concerns;

trait ResponsableData
{
    public function toResponse($request) {}
    protected function calculateResponseStatus(\Illuminate\Http\Request $request): int {}
    public static function allowedRequestIncludes(): ?array {}
    public static function allowedRequestExcludes(): ?array {}
    public static function allowedRequestOnly(): ?array {}
    public static function allowedRequestExcept(): ?array {}
}
