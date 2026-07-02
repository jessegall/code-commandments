<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Bridge\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Bridge\Contract;
use JesseGall\CodeCommandments\Bridge\ContractProvider as BaseProvider;

/**
 * A BACKEND contract provider: reads the PHP AST {@see Codebase} and publishes the
 * cross-cutting facts the backend OWNS — a Spatie `Data` class's shape, say — for the
 * other engine to consume. It composes the same fluent query a backend detector does;
 * it never touches the frontend. The frontend twin is {@see \JesseGall\CodeCommandments\Bridge\Frontend\ContractProvider}.
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
