<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Frontend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Frontend\VueComponents;

final class PropMutation extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'prop-mutation',
            skill: VueComponents::class,
            description: "A prop is WRITTEN — `v-model` bound to it, or `@event=\"prop = …\"` — but props are read-only (a build error or a silent no-op)",
            rule: "Never write a prop. For two-way state use `defineModel`; otherwise emit an `update:` event and let the parent own the value."
        );
    }
}
