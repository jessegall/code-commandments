<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use JesseGall\PhpTypes\Option;

/**
 * A tool this package runs beside itself that may be missing from the machine — found, and built if it has
 * to be, or none.
 */
interface LocatedTool
{
    /**
     * @return Option<static>
     */
    public static function located(): Option;
}
