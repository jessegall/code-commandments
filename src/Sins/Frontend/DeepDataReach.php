<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Frontend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Frontend\VueComponents;

final class DeepDataReach extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'deep-data-reach',
            skill: VueComponents::class,
            description: "An element reaching DEEP into nested data — pass it the mid-object as a prop"
        );
    }
}
