<?php

namespace Shop\CompareSelf;

use JesseGall\CodeCommandments\Sins\Backend\CompareSelf;
use JesseGall\CodeCommandments\Testing\Sinful;

final class SkillOriginCheck
{
    public function __construct(public readonly Origin $origin) {}

    #[Sinful(CompareSelf::class)]
    public function shipped(): bool
    {
        return $this->origin === Origin::Shipped;
    }
}
