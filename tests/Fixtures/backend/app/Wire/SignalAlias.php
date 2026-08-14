<?php

namespace Shop\Wire;

/**
 * Turns a signal class into the short name it travels under. The conversion every binding was spelling
 * before handing its signal over.
 */
final class SignalAlias
{
    public static function of(string $signal): string
    {
        return strtolower(str_replace('\\', '.', $signal));
    }
}
