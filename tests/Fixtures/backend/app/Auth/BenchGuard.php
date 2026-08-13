<?php

namespace Shop\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use JesseGall\CodeCommandments\Sins\Backend\DeNulledFinder;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * The nullable return here is the FRAMEWORK's, not this class's: `Guard::user()` is declared
 * `Authenticatable|null` by the contract, which is called precisely to ask whether anyone is
 * authenticated. Narrowing it would stop the guard fulfilling the contract it exists for, so the
 * fix the rule asks for cannot be made — and every caller de-nulling it is the contract working.
 */
final class BenchGuard implements Guard
{

    #[Righteous(DeNulledFinder::class)]
    public function user(): Authenticatable | null
    {
        return null;
    }

    public function id(): string | null
    {
        return $this->user()?->getAuthIdentifier();
    }

    public function label(): string
    {
        return $this->user()?->getAuthIdentifier() ?? 'anonymous';
    }

}
