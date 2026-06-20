<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

/**
 * The structural archetypes {@see RoleInference} can infer from a class's shape
 * + mutation provenance — the seed of the #135 role-inference catalog (NOT the
 * whole catalog). Kept deliberately small; new archetypes are added as the
 * inference earns them.
 */
enum Archetype: string
{
    /**
     * An encapsulated keyed store: a keyed-array property written by a public
     * mutator AND read back by lookups on the SAME property (the full
     * {@see RegistryShape}). A registry / cache / aggregator.
     */
    case StoreRegistry = 'store_registry';

    /**
     * A mutable bag / config object: a property written DIRECTLY by a public
     * setter (`$this->prop = …`), not funnelled through a keyed store.
     */
    case MutableBag = 'mutable_bag';

    /**
     * An immutable value object / DTO: instance state is never written after
     * construction (never written at all, or written only in the constructor).
     */
    case ImmutableValue = 'immutable_value';

    /**
     * A memo / lazy cache: a keyed array touched only as `$this->p[$k] ??= …`
     * (populate-on-read), never registered into by a plain mutator.
     */
    case Memo = 'memo';

    /**
     * A hand-rolled enum (the pre-8.1 idiom): a private/protected constructor
     * (instances are not freely constructible) plus a CLOSED SET of parameterless
     * public static "case" methods that each build and return a fixed instance
     * (`public static function active(): self { return new self(...); }`). PHP 8.1+
     * native `enum` expresses this with less code, real type safety, and `cases()`.
     */
    case ManualEnum = 'manual_enum';

    /** No confident structural classification. */
    case Unknown = 'unknown';
}
