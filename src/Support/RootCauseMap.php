<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use JesseGall\CodeCommandments\Prophets\Backend\NoNullCoalesceToNullProphet;
use JesseGall\CodeCommandments\Prophets\Backend\NoOptionToNullProphet;
use JesseGall\CodeCommandments\Prophets\Backend\NoSwallowedNotFoundProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferCoercionHelperProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferNativeTypedAccessorProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferEmptyOverNullProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferNullObjectDefaultsProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferOptionOverNullProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferTotalOverNullableProphet;
use JesseGall\CodeCommandments\Prophets\Backend\RegistryReturnContractProphet;
use JesseGall\CodeCommandments\Prophets\Backend\ThrowOnUnhandledCaseProphet;

/**
 * The single source of truth for root-cause ⇄ symptom relationships.
 *
 * One declared relation, keyed cause => [symptoms]. Both directions derive from
 * it so they can never drift:
 *  - {@see symptomsOf()} powers `Commandment::supersedes()` (cause defers symptom);
 *  - {@see causesOf()} powers `Commandment::rootCauses()` (symptom triggers a
 *    cause check that survives `--prophet=` filtering and the `repent` guard).
 *
 * To relate two prophets, add ONE edge here — never hand-override both
 * `supersedes()` and `rootCauses()` on the prophets (that desyncs the directions
 * and breaks the symmetry self-test).
 *
 * The first consumer is the invariant/absence family: an invariant violation
 * (an enum `default => null`, a registry miss, a swallowed not-found, a method
 * every caller de-nulls) is the CAUSE; modelling a genuine absence (Option /
 * Null Object / `?? null`) is the SYMPTOM — correct on its own, a laundering
 * risk when the absence is actually a bug.
 */
final class RootCauseMap
{
    /**
     * Prophets that model a (possibly genuine) absence — the laundering-risk
     * symptoms of every invariant cause below.
     *
     * @var list<class-string>
     */
    private const ABSENCE_SYMPTOMS = [
        PreferOptionOverNullProphet::class,
        PreferEmptyOverNullProphet::class,
        PreferNullObjectDefaultsProphet::class,
        NoOptionToNullProphet::class,
        NoNullCoalesceToNullProphet::class,
    ];

    /** @var array<class-string, list<class-string>>|null memoized flip of relations() */
    private static ?array $causeIndex = null;

    /**
     * The declared cause => symptoms relation.
     *
     * @return array<class-string, list<class-string>>
     */
    public static function relations(): array
    {
        return [
            ThrowOnUnhandledCaseProphet::class => self::ABSENCE_SYMPTOMS,
            PreferTotalOverNullableProphet::class => self::ABSENCE_SYMPTOMS,
            RegistryReturnContractProphet::class => self::ABSENCE_SYMPTOMS,
            NoSwallowedNotFoundProphet::class => self::ABSENCE_SYMPTOMS,

            // When the receiver is a typed bag, "use its native accessor" is the
            // root fix; PreferCoercionHelper's "hoist the repeated coercion into
            // a T_*::coerce() helper" would otherwise suggest the OPPOSITE on the
            // very same guard-ternary. Defer it in-region so they never collide.
            PreferNativeTypedAccessorProphet::class => [
                PreferCoercionHelperProphet::class,
            ],
        ];
    }

    /**
     * Symptom prophet classes this cause defers (its `supersedes()` list).
     *
     * @param  class-string  $cause
     * @return list<class-string>
     */
    public static function symptomsOf(string $cause): array
    {
        return self::relations()[$cause] ?? [];
    }

    /**
     * Cause prophet classes that are the likely root cause of this symptom (its
     * `rootCauses()` list) — the flipped index.
     *
     * @param  class-string  $symptom
     * @return list<class-string>
     */
    public static function causesOf(string $symptom): array
    {
        if (self::$causeIndex === null) {
            $index = [];

            foreach (self::relations() as $cause => $symptoms) {
                foreach ($symptoms as $sym) {
                    $index[$sym][] = $cause;
                }
            }

            self::$causeIndex = $index;
        }

        return self::$causeIndex[$symptom] ?? [];
    }
}
