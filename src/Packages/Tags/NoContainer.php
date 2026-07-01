<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Packages\Tags;

use JesseGall\CodeCommandments\Packages\Exemption;

/**
 * Exemption tag: a type the framework instantiates ITSELF with no container/DI (an Eloquent cast).
 * There's nothing to inject, so a loose array/primitive parameter is the framework's calling
 * convention — read by array-bag.
 */
final class NoContainer extends Exemption
{
    public function slug(): string
    {
        return 'no-container';
    }

    public function description(): string
    {
        return 'A type the framework instantiates itself, no container/DI (an Eloquent cast) — a loose array parameter is the framework\'s convention, exempt from array-bag.';
    }
}
