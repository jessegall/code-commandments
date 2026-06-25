<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Sinful\RepeatedFallbackCrossFile;

class Pipeline
{
    public static function current(): ?self
    {
        return null;
    }

    public function child(): ?self
    {
        return null;
    }

    public static function make(): self
    {
        return new self;
    }
}
