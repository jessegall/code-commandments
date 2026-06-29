<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Scope;

/**
 * The default scope: no restriction — every finding in the parsed path is reported.
 */
final class EntireCodebase implements ChangeScope
{
    public function restrictTo(string $path): ?array
    {
        return null;
    }
}
