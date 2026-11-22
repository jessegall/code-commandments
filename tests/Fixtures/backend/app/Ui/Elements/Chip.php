<?php

namespace Shop\Ui\Elements;

// @sin NamespaceDependency
use Shop\Ui\Shared\Panel;

/**
 * Inheritance is the loudest arrow there is: a primitive that IS a Panel has swapped the two
 * layers around, and every Panel change now reaches down into Elements.
 */
final class Chip extends Panel
{
    public function tone(): string
    {
        return 'muted';
    }
}
