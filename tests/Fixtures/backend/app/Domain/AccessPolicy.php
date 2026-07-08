<?php

namespace Shop\Domain;

use JesseGall\CodeCommandments\Sins\Backend\RepeatedGuard;
use JesseGall\CodeCommandments\Testing\Sinful;

/*
 * The SAME `$user->active && $account->verified` guard, spelled two ways: `allow` inlines the reaches,
 * `audit` stashes each in a local first. The canonical fingerprint inlines the single-assignment locals,
 * so both count — a copied condition, whichever spelling.
 */
final class AccessPolicy
{
    #[Sinful(RepeatedGuard::class)]
    public function allow($user, $account): bool
    {
        return $user->active && $account->verified;
    }

    #[Sinful(RepeatedGuard::class)]
    public function audit($user, $account): string
    {
        $live = $user->active;
        $ok = $account->verified;

        return $live && $ok ? 'granted' : 'denied';
    }

    public function describe(string $role, int $level): string
    {
        return ucfirst($role) . ' (tier ' . str_pad((string) $level, 2, '0', STR_PAD_LEFT) . ')';
    }

    public function escalate(int $current, int $ceiling): int
    {
        return min($current + 1, $ceiling);
    }
}
