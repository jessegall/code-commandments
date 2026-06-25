<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Sinful\PreferDataTransformers;

class FooData extends \Spatie\LaravelData\Data
{
    public function __construct(
        public string $name,
        public string $type,
        public bool $required,
    ) {}
}
