<?php

namespace Shop\Support;

use JesseGall\CodeCommandments\Sins\Backend\RedundantElse;

use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Resolves an access level — the `if` already returns, so the trailing `else` is
 * dead weight. The righteous twin (`level`) drops it.
 */
final class AccessLevel
{
    #[Sinful(RedundantElse::class)]
    public function resolve(bool $authenticated, int $role): int
    {
        if (! $authenticated) {
            return 0;
        } else {
            return $role;
        }
    }

    public function level(bool $authenticated, int $role): int
    {
        if (! $authenticated) {
            return 0;
        }

        return $role;
    }
}
