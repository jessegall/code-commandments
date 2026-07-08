<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

/**
 * The last segment of a fully-qualified class name — `App\Data\OrderData` becomes `OrderData`. The one home
 * for a computation a dozen places used to each keep a private copy of; pass `$object::class` for an
 * instance. Pure string work, no reflection, so it is safe for any layer to call.
 */
final class ClassName
{
    public static function short(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts);
    }
}
