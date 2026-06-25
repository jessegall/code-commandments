<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Sinful\RepeatedFallbackCrossFile;

class TernaryConsumerA
{
    public function run(): Pipeline
    {
        return Pipeline::current() === null ? Pipeline::make() : Pipeline::current();
    }
}
