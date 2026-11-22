<?php

namespace Spatie\LaravelData\Concerns;

trait TransformableData
{
    public function transform(\Spatie\LaravelData\Support\Transformation\TransformationContextFactory|\Spatie\LaravelData\Support\Transformation\TransformationContext|null $transformationContext = NULL): array {}
    public function all(): array {}
    public function toArray(): array {}
    public function toJson($options = 0): string {}
    public function jsonSerialize(): array {}
    public static function castUsing(array $arguments) {}
}
