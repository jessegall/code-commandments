<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Scope;

use JesseGall\CodeCommandments\Ast\Support\Frozen;

/**
 * The scope of NOT-frozen files — compounded into a run's {@see Scope} so a file declared immutable
 * ({@see Frozen}) is never a target. The frozen verdict is read from source once per file and remembered.
 */
final class FrozenScope implements FileScope
{
    /** @var array<string, bool>  realpath => is the file frozen (read once) */
    private array $frozen = [];

    public function includes(string $path): bool
    {
        $real = realpath($path);

        if ($real === false) {
            return true; // no file on disk to carry a marker — nothing to exclude
        }

        return ! ($this->frozen[$real] ??= Frozen::isFrozen((string) @file_get_contents($real)));
    }
}
