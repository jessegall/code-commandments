<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

use InvalidArgumentException;

/**
 * An unrecognised directive string, such as a typo'd `'v-fi'`. Carries the known directives, so
 * the message shows the author their near-miss.
 */
final class UnknownDirective extends InvalidArgumentException
{
    /**
     * @param  list<string>  $known
     */
    public static function for(string $directive, array $known): self
    {
        return new self("Unknown Vue directive: {$directive} (known: " . implode(', ', $known) . ').');
    }
}
