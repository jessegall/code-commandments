<?php

namespace Shop\CompareSelf;

use JesseGall\CodeCommandments\Sins\Backend\CompareSelf;
use JesseGall\CodeCommandments\Testing\Sinful;

enum LoadState: string
{
    case Empty = 'empty';
    case Ready = 'ready';

    public function notEquals(self $other): bool
    {
        return $this !== $other;
    }
}

#[Sinful(CompareSelf::class)]
function canLoad(?LoadState $state): bool
{
    return LoadState::Empty !== $state;
}
