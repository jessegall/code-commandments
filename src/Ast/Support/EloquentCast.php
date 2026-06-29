<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use JesseGall\CodeCommandments\Ast\Codebase;

/**
 * Is a class an Eloquent attribute cast? Eloquent `new`-instantiates a cast (`'col'
 * => MyCast::class`) with no container and no constructor DI, and dictates its method
 * signatures (`get`/`set($model, $key, $value, $attributes)`) — so a cast must reach
 * services through facades and read the framework's raw `$attributes` array by key.
 * Several detectors exempt that seam; this is the shared "is it a cast?" check
 * (by the contract it implements — semantic, not a name).
 */
final class EloquentCast
{
    private const array CONTRACTS = [
        'Illuminate\\Contracts\\Database\\Eloquent\\CastsAttributes',
        'Illuminate\\Contracts\\Database\\Eloquent\\CastsInboundAttributes',
    ];

    public static function is(Codebase $codebase, ?string $class): bool
    {
        foreach (self::CONTRACTS as $contract) {
            if ($codebase->implements($class, $contract)) {
                return true;
            }
        }

        return false;
    }
}
