<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Bridge\Frontend;

use JesseGall\CodeCommandments\Bridge\Contract;
use JesseGall\CodeCommandments\Bridge\ContractProvider as BaseProvider;
use JesseGall\CodeCommandments\Vue\Codebase;

/**
 * A FRONTEND contract provider: reads the Vue {@see Codebase} and publishes the
 * cross-cutting facts the frontend OWNS for the other engine to consume. The mirror
 * of {@see \JesseGall\CodeCommandments\Bridge\Backend\ContractProvider} — same base
 * contract, over Vue instead of PHP. None ship yet; the seam exists so the {@see \JesseGall\CodeCommandments\Bridge\Bridge}
 * stays symmetric the day the frontend needs to publish.
 */
interface ContractProvider extends BaseProvider
{
    /**
     * The contracts this engine publishes from $codebase.
     *
     * @return list<Contract>
     */
    public function contracts(Codebase $codebase): array;
}
