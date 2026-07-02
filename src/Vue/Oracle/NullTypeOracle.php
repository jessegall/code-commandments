<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Oracle;

/**
 * The default oracle: it resolves nothing. A codebase without `vue-tsc` gets exactly the AST's
 * sound inference and no more — the checker is a bonus, never a dependency.
 */
final class NullTypeOracle implements TypeOracle
{
    public function resolveAll(array $queries): array
    {
        return [];
    }
}
