<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Sinful\RepeatedFallbackCrossFile;

class TernaryConsumerB
{
    public function build(): Pipeline
    {
        return Pipeline::current() === null ? Pipeline::make() : Pipeline::current();
    }
}
