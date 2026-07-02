<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Bridge;

use JesseGall\CodeCommandments\Ast\Codebase as BackendCodebase;
use JesseGall\CodeCommandments\Vue\Codebase as FrontendCodebase;

/**
 * The meeting point of the two engines — the ONLY place that holds both codebases at
 * once. It asks every {@see ContractProvider} for the {@see Contract}s its engine
 * publishes and collects them into one {@see Contracts} bag for the consumers
 * ({@see ConsumesContracts}). Neither engine references the other; the Bridge, and
 * the neutral runner that calls it, are the seam.
 */
final class Bridge
{
    /**
     * Every contract both engines publish this run. A codebase is optional so a
     * single-engine run gathers only what it can.
     */
    public static function gather(?BackendCodebase $backend = null, ?FrontendCodebase $frontend = null): Contracts
    {
        $contracts = new Contracts();

        if ($backend !== null) {
            foreach (Catalog::backend() as $provider) {
                $contracts = $contracts->with(...$provider->contracts($backend));
            }
        }

        if ($frontend !== null) {
            foreach (Catalog::frontend() as $provider) {
                $contracts = $contracts->with(...$provider->contracts($frontend));
            }
        }

        return $contracts;
    }
}
