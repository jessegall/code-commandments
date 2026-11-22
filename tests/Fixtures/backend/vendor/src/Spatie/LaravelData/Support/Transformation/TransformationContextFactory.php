<?php

namespace Spatie\LaravelData\Support\Transformation;

class TransformationContextFactory
{
    use \Spatie\LaravelData\Support\Partials\ForwardsToPartialsDefinition;
    public ?int $maxDepth;
    public bool $throwWhenMaxDepthReached;
    public bool $transformValues;
    public bool $mapPropertyNames;
    public \Spatie\LaravelData\Support\Wrapping\WrapExecutionType $wrapExecutionType;
    public ?\Spatie\LaravelData\Support\Transformation\GlobalTransformersCollection $transformers;
    public ?\Spatie\LaravelData\Support\Partials\PartialsCollection $includePartials;
    public ?\Spatie\LaravelData\Support\Partials\PartialsCollection $excludePartials;
    public ?\Spatie\LaravelData\Support\Partials\PartialsCollection $onlyPartials;
    public ?\Spatie\LaravelData\Support\Partials\PartialsCollection $exceptPartials;
    public static function create(): \Spatie\LaravelData\Support\Transformation\TransformationContextFactory {}
    protected function __construct(bool $transformValues = true, bool $mapPropertyNames = true, \Spatie\LaravelData\Support\Wrapping\WrapExecutionType $wrapExecutionType = \Spatie\LaravelData\Support\Wrapping\WrapExecutionType::Disabled, ?\Spatie\LaravelData\Support\Transformation\GlobalTransformersCollection $transformers = NULL, ?\Spatie\LaravelData\Support\Partials\PartialsCollection $includePartials = NULL, ?\Spatie\LaravelData\Support\Partials\PartialsCollection $excludePartials = NULL, ?\Spatie\LaravelData\Support\Partials\PartialsCollection $onlyPartials = NULL, ?\Spatie\LaravelData\Support\Partials\PartialsCollection $exceptPartials = NULL) {}
    public function get(\Spatie\LaravelData\Contracts\IncludeableData $data): \Spatie\LaravelData\Support\Transformation\TransformationContext {}
    public function withValueTransformation(bool $transformValues = true): static {}
    public function withoutValueTransformation(bool $withoutValueTransformation = true): static {}
    public function withPropertyNameMapping(bool $mapPropertyNames = true): static {}
    public function withoutPropertyNameMapping(bool $withoutPropertyNameMapping = true): static {}
    public function withWrapExecutionType(\Spatie\LaravelData\Support\Wrapping\WrapExecutionType $wrapExecutionType): static {}
    public function withoutWrapping(): static {}
    public function withWrapping(): static {}
    public function withTransformer(string $transformable, \Spatie\LaravelData\Transformers\Transformer|string $transformer): static {}
    public function maxDepth(?int $maxDepth, bool $throw = true): static {}
    public function mergeIncludePartials(\Spatie\LaravelData\Support\Partials\PartialsCollection $partials): static {}
    public function mergeExcludePartials(\Spatie\LaravelData\Support\Partials\PartialsCollection $partials): static {}
    public function mergeOnlyPartials(\Spatie\LaravelData\Support\Partials\PartialsCollection $partials): static {}
    public function mergeExceptPartials(\Spatie\LaravelData\Support\Partials\PartialsCollection $partials): static {}
    protected function getPartialsContainer(): object {}
}
