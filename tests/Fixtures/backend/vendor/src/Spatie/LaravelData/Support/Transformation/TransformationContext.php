<?php

namespace Spatie\LaravelData\Support\Transformation;

class TransformationContext implements \Stringable
{
    public bool $transformValues;
    public bool $mapPropertyNames;
    public \Spatie\LaravelData\Support\Wrapping\WrapExecutionType $wrapExecutionType;
    public ?\Spatie\LaravelData\Support\Transformation\GlobalTransformersCollection $transformers;
    public ?\Spatie\LaravelData\Support\Partials\PartialsCollection $includePartials;
    public ?\Spatie\LaravelData\Support\Partials\PartialsCollection $excludePartials;
    public ?\Spatie\LaravelData\Support\Partials\PartialsCollection $onlyPartials;
    public ?\Spatie\LaravelData\Support\Partials\PartialsCollection $exceptPartials;
    public int $depth;
    public ?int $maxDepth;
    public bool $throwWhenMaxDepthReached;
    public function __construct(bool $transformValues = true, bool $mapPropertyNames = true, \Spatie\LaravelData\Support\Wrapping\WrapExecutionType $wrapExecutionType = \Spatie\LaravelData\Support\Wrapping\WrapExecutionType::Disabled, ?\Spatie\LaravelData\Support\Transformation\GlobalTransformersCollection $transformers = NULL, ?\Spatie\LaravelData\Support\Partials\PartialsCollection $includePartials = NULL, ?\Spatie\LaravelData\Support\Partials\PartialsCollection $excludePartials = NULL, ?\Spatie\LaravelData\Support\Partials\PartialsCollection $onlyPartials = NULL, ?\Spatie\LaravelData\Support\Partials\PartialsCollection $exceptPartials = NULL, int $depth = 0, ?int $maxDepth = NULL, bool $throwWhenMaxDepthReached = true) {}
    public function setWrapExecutionType(\Spatie\LaravelData\Support\Wrapping\WrapExecutionType $wrapExecutionType): \Spatie\LaravelData\Support\Transformation\TransformationContext {}
    public function addIncludedPartial(\Spatie\LaravelData\Support\Partials\Partial ...$partials): void {}
    public function addExcludedPartial(\Spatie\LaravelData\Support\Partials\Partial ...$partials): void {}
    public function addOnlyPartial(\Spatie\LaravelData\Support\Partials\Partial ...$partials): void {}
    public function addExceptPartial(\Spatie\LaravelData\Support\Partials\Partial ...$partials): void {}
    public function mergeIncludedPartials(\Spatie\LaravelData\Support\Partials\PartialsCollection $partials): void {}
    public function mergeExcludedPartials(\Spatie\LaravelData\Support\Partials\PartialsCollection $partials): void {}
    public function mergeOnlyPartials(\Spatie\LaravelData\Support\Partials\PartialsCollection $partials): void {}
    public function mergeExceptPartials(\Spatie\LaravelData\Support\Partials\PartialsCollection $partials): void {}
    public function rollBackPartialsWhenRequired(): void {}
    public function mergePartialsFromDataContext(\Spatie\LaravelData\Contracts\IncludeableData&\Spatie\LaravelData\Contracts\ContextableData $data): \Spatie\LaravelData\Support\Transformation\TransformationContext {}
    public function hasPartials(): bool {}
    public function toArray(): array {}
    public function __clone(): void {}
    public function __toString(): string {}
}
