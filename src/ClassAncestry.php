<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

/**
 * Whatever can answer "does this class descend from that one" — the whole-program class graph,
 * narrowed to the one question a caller outside the engine needs.
 *
 * It exists so a package's exemption clause can ask it without naming {@see Ast\Codebase}: a clause
 * selects types by their base, and that is ALL it needs, while the scan that answers it also needs
 * to know which exemptions are in force. Typed as the whole codebase, those two facts pointed at
 * each other.
 */
interface ClassAncestry
{
    /**
     * Is $class $base, or anything descending from it (through `extends` or `implements`)? False
     * for a class that cannot be named — nothing is a descendant of nothing.
     */
    public function isA(?string $class, string $base): bool;
}
