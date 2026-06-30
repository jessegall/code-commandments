<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Frontend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Frontend\VueComponents;

final class CompoundInlineComponent extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'compound-inline-component',
            skill: VueComponents::class,
            description: "A compound primitive (`Dialog`/`Card`/`Sheet`/`Tabs`…) assembled INLINE with a substantial body — extract it into its own named component"
        );
    }
}
