<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Results;

/**
 * Structured "this symptom has an unresolved root cause" annotation attached to
 * a {@see Finding} by the root-cause resolver. Kept as data — the presenter
 * formats it into the user-facing block — so the ordering/serialisation layers
 * never carry CLI prose.
 *
 * @see \JesseGall\CodeCommandments\Support\RootCauseResolver
 */
final class RootCauseHint
{
    /**
     * @param class-string Fully-qualified cause prophet class. $causeClass
     */
    public function __construct(
        /** Short class name of the matched cause prophet (for scripture pointers). */
        public readonly string $causeShort,
        public readonly string $causeClass,
        /** One-line "why this absence is an invariant violation, not genuine". */
        public readonly string $reason,
    ) {}
}
