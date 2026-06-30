<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Frontend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Frontend\VueComponents;

final class PropDrilling extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'prop-drilling',
            skill: VueComponents::class,
            description: "A prop is forwarded straight to a child component and used NOWHERE else — the component is a pass-through pipe",
            rule: "Don't thread a prop through a component that doesn't use it; provide/inject it, or give the child the data directly."
        );
    }
}
