<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Sinful\RepeatedFallbackCrossFile;

class ConsumerA
{
    public function run(): Pipeline
    {
        return Pipeline::current()?->child() ?? Pipeline::make();
    }
}
