<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Oracle;

use JesseGall\CodeCommandments\Vue\Sfc;

/**
 * The last resort when the AST cannot soundly type a local: ask a real type checker. Resolves
 * MANY components' still-`unknown` locals in ONE pass — a checker types the whole program at once,
 * so we ask it once, never per component. The default {@see NullTypeOracle} resolves nothing (the
 * engine never REQUIRES a checker); a {@see VueTscOracle} is wired in only when the target ships
 * `vue-tsc`.
 */
interface TypeOracle
{
    /**
     * The resolved types for every query, in ONE checker run — each query a component and the local
     * names still typed `unknown` in it. Omits any name it cannot resolve (the caller keeps its
     * `unknown`). Never throws; an absent or failing checker returns an empty map.
     *
     * @param  array<string, array{sfc: Sfc, names: list<string>}>  $queries  keyed by component path
     * @return array<string, array<string, string>>  component path => (local name => resolved type)
     */
    public function resolveAll(array $queries): array;
}
