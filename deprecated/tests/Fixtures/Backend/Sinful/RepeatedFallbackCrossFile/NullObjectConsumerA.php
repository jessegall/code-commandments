<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Sinful\RepeatedFallbackCrossFile;

class NullObjectConsumerA
{
    public function run(): Pipeline
    {
        // Repeated, but the fallback is a Null Object — defer to PreferNullObjectDefaults.
        return Pipeline::current() ?? new NullPipeline;
    }
}
