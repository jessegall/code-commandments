<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Sinful\StringsThatShouldBeEnumsCrossFile;

class WalkerCaller
{
    public function __construct(
        private readonly Walker $walker,
    ) {}

    public function run(): void
    {
        $this->walker->step('Run');
        $this->walker->step('Walk');
        $this->walker->step('Run');
    }
}
