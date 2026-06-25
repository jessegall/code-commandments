<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Sinful\StringsThatShouldBeEnumsCrossFile;

class ToggleCaller
{
    public function __construct(
        private readonly Toggle $toggle,
    ) {}

    public function run(): void
    {
        $this->toggle->flip('on');
        $this->toggle->flip('off');
        $this->toggle->flip('on');
    }
}
