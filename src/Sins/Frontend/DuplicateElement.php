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
            description: "Identical markup repeated across the template — extract one component"
        );
    }
}
