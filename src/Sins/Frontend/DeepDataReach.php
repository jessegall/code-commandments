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
            description: "A CLUSTER of elements in a sizeable template all reaching deep into the same nested object (≥2 distinct fields) — extract the shared mid-object into a component that takes it as a prop",
            rule: "Pass the mid-object as a prop; don't reach deep into nested data from the template."
        );
    }
}
