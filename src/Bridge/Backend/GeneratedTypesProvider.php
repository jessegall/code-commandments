<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Bridge\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Spatie\TransformerOutput;
use JesseGall\CodeCommandments\Bridge\GeneratedTypes;

/**
 * Publishes where the Spatie TypeScript transformer writes its generated types — read
 * from the project's own configuration ({@see TransformerOutput}). A frontend detector
 * uses it to exempt the generator's own output from a "hand-copied server type" finding:
 * that file is the fix, not the sin.
 */
final class GeneratedTypesProvider implements ContractProvider
{
    public function contracts(Codebase $codebase): array
    {
        $location = TransformerOutput::locationIn($codebase);

        return $location === null ? [] : [new GeneratedTypes($location)];
    }
}
