<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Sinful\RepeatedFallbackCrossFile;

class NullObjectConsumerB
{
    public function build(): Pipeline
    {
        return Pipeline::current() ?? new NullPipeline;
    }
}
