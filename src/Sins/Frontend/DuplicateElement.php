<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Frontend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Frontend\VueComponents;

final class DuplicateElement extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'duplicate-element',
            skill: VueComponents::class,
            description: "Identical markup (3+ elements) repeated 2+ times — within a template or across components — extract one component",
            rule: "Extract repeated identical markup into one component."
        );
    }
}
