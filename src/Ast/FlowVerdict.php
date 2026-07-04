<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast;

/**
 * The result of a {@see ValueFlow} forward walk from a value slot: how many of the places the value
 * reaches ASSUME it is present (un-guarded dereference, or landing in a non-nullable parameter) vs
 * ACKNOWLEDGE it can be null (any null-guard or truthiness test). A caller decides what to do with
 * the counts — e.g. "phantom" is `assume >= 1 && guard == 0` (nothing anywhere admits the null).
 */
final class FlowVerdict
{
    public function __construct(
        public readonly int $assume,
        public readonly int $guard,
    ) {}
}
