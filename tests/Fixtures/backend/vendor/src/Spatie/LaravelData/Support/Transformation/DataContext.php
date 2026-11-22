<?php

namespace Spatie\LaravelData\Support\Transformation;

class DataContext
{
    public ?\Spatie\LaravelData\Support\Partials\PartialsCollection $includePartials;
    public ?\Spatie\LaravelData\Support\Partials\PartialsCollection $excludePartials;
    public ?\Spatie\LaravelData\Support\Partials\PartialsCollection $onlyPartials;
    public ?\Spatie\LaravelData\Support\Partials\PartialsCollection $exceptPartials;
    public ?\Spatie\LaravelData\Support\Wrapping\Wrap $wrap;
    public function __construct(?\Spatie\LaravelData\Support\Partials\PartialsCollection $includePartials, ?\Spatie\LaravelData\Support\Partials\PartialsCollection $excludePartials, ?\Spatie\LaravelData\Support\Partials\PartialsCollection $onlyPartials, ?\Spatie\LaravelData\Support\Partials\PartialsCollection $exceptPartials, ?\Spatie\LaravelData\Support\Wrapping\Wrap $wrap = NULL) {}
    public function mergePartials(\Spatie\LaravelData\Support\Transformation\DataContext $dataContext): \Spatie\LaravelData\Support\Transformation\DataContext {}
    public function mergeTransformationContext(\Spatie\LaravelData\Support\Transformation\TransformationContext $context): \Spatie\LaravelData\Support\Transformation\DataContext {}
    public function getRequiredPartialsAndRemoveTemporaryOnes(\Spatie\LaravelData\Contracts\IncludeableData $data, \Spatie\LaravelData\Support\Partials\PartialsCollection $partials): \Spatie\LaravelData\Support\Partials\PartialsCollection {}
    public function toSerializedArray(): array {}
    public static function fromSerializedArray(array $content): \Spatie\LaravelData\Support\Transformation\DataContext {}
}
