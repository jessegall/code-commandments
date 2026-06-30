<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Frontend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Frontend\VueControlFlow;

final class SwitchCase extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'switch-case',
            skill: VueControlFlow::class,
            description: "A `v-if`/`v-else-if` chain re-testing the same subject (should be `<SwitchCase :value>`)",
            rule: "Dispatch on a value with `<SwitchCase :value>` (a slot per case); never a `v-if`/`v-else-if` chain re-testing the same subject."
        );
    }
}
