<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Sinful\StringsThatShouldBeEnumsCrossFile;

class ValueBagCaller
{
    public function __construct(
        private readonly ValueBag $bag,
    ) {}

    public function run(): void
    {
        // A small, case-name-shaped set of literals — exactly the trap. The
        // accessor key space is still open, so this must not be flagged.
        $this->bag->asFloat('value');
        $this->bag->asFloat('compareTo');
        $this->bag->asFloat('threshold');
        $this->bag->asFloat('weight');
    }
}
