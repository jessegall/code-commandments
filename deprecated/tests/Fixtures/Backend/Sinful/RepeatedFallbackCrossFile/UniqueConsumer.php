<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Sinful\RepeatedFallbackCrossFile;

class UniqueConsumer
{
    public function run(): Pipeline
    {
        // Qualifies structurally, but this exact chain appears only once.
        return Pipeline::current()?->onlyHere() ?? Pipeline::make();
    }
}
