<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Results;

/**
 * The structural altitude of a commandment, used to order findings so the
 * most root-cause fixes are presented first. A lower weight is fixed
 * earlier.
 *
 *   Structural  — changes shape/types (array bags, nullable decisions)
 *   Correctness — behaviour/safety (raw request input, event dispatch)
 *   Convention  — naming/contracts (kebab routes, typed getters)
 *   Cosmetic    — surface polish (sprintf, docblock length)
 */
enum Tier: string
{
    /** Changes the shape or types of the code — array bags, nullable decisions, value objects. The deepest root cause, so it sorts first. */
    case Structural = 'structural';

    /** Affects behaviour or safety — raw request input, event dispatch, unhandled cases. Fixed after structure but before naming. */
    case Correctness = 'correctness';

    /** Naming and contracts — kebab routes, typed getters, documented cases. A surface-of-the-API concern, addressed after correctness. */
    case Convention = 'convention';

    /** Surface polish — sprintf vs interpolation, docblock length. No behavioural weight, so it sorts last. */
    case Cosmetic = 'cosmetic';

    /**
     * Sort weight — lower is addressed first.
     */
    public function weight(): int
    {
        return match ($this) {
            self::Structural => 0,
            self::Correctness => 1,
            self::Convention => 2,
            self::Cosmetic => 3,
        };
    }

    /**
     * Resolve a tier from a case-insensitive name, or null if unrecognised.
     * Used to honour a `tier` override in a prophet's config.
     */
    public static function tryFromName(string $name): ?self
    {
        foreach (self::cases() as $case) {
            if (strcasecmp($case->value, $name) === 0 || strcasecmp($case->name, $name) === 0) {
                return $case;
            }
        }

        return null;
    }
}
