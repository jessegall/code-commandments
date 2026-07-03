<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use RuntimeException;

/**
 * A `.commandments/config.php` a scribe can't wire into.
 */
final class UnrecognizableConfig extends RuntimeException
{
    public static function at(string $path): self
    {
        return new self("{$path} does not return a `function (Config \$config)` closure. Restore it, or delete the file to regenerate.");
    }
}
