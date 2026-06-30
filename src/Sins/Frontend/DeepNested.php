<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Frontend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Frontend\VueComponents;

final class DeepNested extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'deep-nested',
            skill: VueComponents::class,
            description: "Template markup nested far too deep — extract a subtree as its own component"
        );
    }
}
