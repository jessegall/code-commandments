<?php

namespace Shop\Ui\Elements;

use JesseGall\CodeCommandments\Sins\Backend\NamespaceDependency;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Ui\Shared\Panel;

/**
 * Inheritance is the loudest arrow there is: a primitive that IS a Panel has swapped the two
 * layers around, and every Panel change now reaches down into Elements.
 */
#[Sinful(NamespaceDependency::class)]
final class Chip extends Panel
{
    public function tone(): string
    {
        return 'muted';
    }
}
