<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Oracle;

use JesseGall\CodeCommandments\Vue\Sfc;

/**
 * The last resort when the AST cannot soundly type a local: ask a real type checker. Given a
 * component and the local names still typed `unknown`, return the ones it could resolve. The
 * default {@see NullTypeOracle} resolves nothing — the engine never REQUIRES a checker; a
 * {@see VueTscOracle} is wired in only when the target project ships `vue-tsc`.
 */
interface TypeOracle
{
    /**
     * The resolved TS type of each name it can type, in $component's `<script setup>` scope —
     * omitting any it cannot (the caller keeps its `unknown`). Never throws; a checker that is
     * absent or fails returns an empty map.
     *
     * @param  list<string>  $names
     * @return array<string, string>  local name => resolved type
     */
    public function resolve(Sfc $component, array $names): array;
}
