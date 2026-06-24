<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Doctrines;

use JesseGall\CodeCommandments\Prophets\Backend\NoCoalesceOnNonNullableProphet;
use JesseGall\CodeCommandments\Prophets\Backend\NoNullCoalesceToNullProphet;
use JesseGall\CodeCommandments\Prophets\Backend\NoOptionToNullProphet;
use JesseGall\CodeCommandments\Prophets\Backend\NoSwallowedNotFoundProphet;
use JesseGall\CodeCommandments\Prophets\Backend\DataClassFromArrayOnlyProphet;
use JesseGall\CodeCommandments\Prophets\Backend\DataClumpToValueObjectProphet;
use JesseGall\CodeCommandments\Prophets\Backend\EagerRegistryProphet;
use JesseGall\CodeCommandments\Prophets\Backend\NoArrayBagProphet;
use JesseGall\CodeCommandments\Prophets\Backend\NoAuthUserInDataClassesProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferClassifierCompositionProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferCoalesceFactoryProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferCoalesceForProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferCoalescingFactoryProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferConfigDrivenRegistryProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferEmptyOverNullProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferInterfaceOverTypeListProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferNativeTypedAccessorProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferNullObjectDefaultsProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferOptionOverNullProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferTotalOverNullableProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferTypeCoalesceProphet;
use JesseGall\CodeCommandments\Prophets\Backend\RegistryBaseBypassProphet;
use JesseGall\CodeCommandments\Prophets\Backend\RegistryNamingHonestyProphet;
use JesseGall\CodeCommandments\Prophets\Backend\RegistryPatternProphet;
use JesseGall\CodeCommandments\Prophets\Backend\RegistryPurityProphet;
use JesseGall\CodeCommandments\Prophets\Backend\RegistryReturnContractProphet;
use JesseGall\CodeCommandments\Prophets\Backend\ResolverNamingHonestyProphet;
use JesseGall\CodeCommandments\Prophets\Backend\ResolverPatternProphet;
use JesseGall\CodeCommandments\Prophets\Backend\SetNamingHonestyProphet;
use JesseGall\CodeCommandments\Prophets\Backend\SetReturnContractProphet;
use JesseGall\CodeCommandments\Prophets\Backend\ThrowOnUnhandledCaseProphet;

/**
 * The catalogue of doctrines and the inverted index that locates a prophet's
 * doctrine + band. The single source of truth for cascade ordering (engine-
 * internal — consumers select prophets, never reorder). Doctrines are re-homed
 * here one at a time during the v3 rebuild; a prophet not in any doctrine is a
 * singleton (never suppresses, never suppressed).
 */
final class DoctrineRegistry
{
    /** @var array<string, array{doctrine: string, band: int}>|null */
    private static ?array $index = null;

    /**
     * @return list<Doctrine>
     */
    public static function all(): array
    {
        return [
            // TOTALITY — drive absence-handling to a TOTAL source. Coarse → fine:
            // boundary → invariant causes → source totality → dead coalesce →
            // coalesce-factory → hygiene nitpick. The T_*::coalesce nitpick only
            // ever fires on a coalesce that survived everything above it.
            new Doctrine('totality', [
                [PreferNativeTypedAccessorProphet::class],
                [ThrowOnUnhandledCaseProphet::class, RegistryReturnContractProphet::class, NoSwallowedNotFoundProphet::class],
                [PreferTotalOverNullableProphet::class, PreferOptionOverNullProphet::class, PreferEmptyOverNullProphet::class, PreferNullObjectDefaultsProphet::class],
                [NoCoalesceOnNonNullableProphet::class, NoNullCoalesceToNullProphet::class, NoOptionToNullProphet::class],
                [PreferCoalesceFactoryProphet::class, PreferCoalescingFactoryProphet::class],
                [PreferTypeCoalesceProphet::class, PreferCoalesceForProphet::class],
            ]),

            // ROLES — a class that PLAYS a structural role (Registry / Set / Resolver)
            // should declare it, then honour its contract. Coarse → fine: extract the
            // shared base → name the role honestly → honour the return/purity contract
            // → the finer refinements (eager, config-driven). Shape detection is shared
            // with the engine via RegistryShape/SetShape (the corpus-grounded rewrite).
            new Doctrine('roles', [
                [RegistryPatternProphet::class],
                [RegistryNamingHonestyProphet::class, SetNamingHonestyProphet::class, ResolverNamingHonestyProphet::class, ResolverPatternProphet::class],
                [RegistryReturnContractProphet::class, SetReturnContractProphet::class, RegistryPurityProphet::class, RegistryBaseBypassProphet::class],
                [EagerRegistryProphet::class, PreferConfigDrivenRegistryProphet::class],
            ]),

            // CLASSIFICATION — decide "which kind is this?" by the TYPE, not a hardcoded
            // name list. Coarse → fine: replace the type-name list with a marker
            // interface / Classifier → then compose classifiers rather than re-listing.
            new Doctrine('classification', [
                [PreferInterfaceOverTypeListProphet::class],
                [PreferClassifierCompositionProphet::class],
            ]),

            // VALUE OBJECTS — model structured data as a typed value object, not loose
            // primitives or an untyped array. Coarse → fine: INTRODUCE the type (a data
            // clump that travels together, or an `array<string,mixed>` bag, becomes a
            // value object) → then the resulting data class honours its contract
            // (array-constructible) and stays pure (no auth user / framework state
            // smuggled in). Introduce the value object first; its hygiene rules only
            // matter once it exists.
            new Doctrine('value-objects', [
                [DataClumpToValueObjectProphet::class, NoArrayBagProphet::class],
                [DataClassFromArrayOnlyProphet::class, NoAuthUserInDataClassesProphet::class],
            ]),
        ];
    }

    /**
     * The doctrine + band a prophet belongs to, or null when it is a singleton.
     *
     * @return array{doctrine: string, band: int}|null
     */
    public static function locate(string $prophetClass): ?array
    {
        return self::index()[$prophetClass] ?? null;
    }

    public static function doctrineOf(string $prophetClass): ?string
    {
        return self::locate($prophetClass)['doctrine'] ?? null;
    }

    public static function bandOf(string $prophetClass): ?int
    {
        return self::locate($prophetClass)['band'] ?? null;
    }

    /**
     * @return array<string, array{doctrine: string, band: int}>
     */
    private static function index(): array
    {
        if (self::$index !== null) {
            return self::$index;
        }

        $index = [];

        foreach (self::all() as $doctrine) {
            foreach ($doctrine->bands as $band => $members) {
                foreach ($members as $member) {
                    $index[$member] = ['doctrine' => $doctrine->name, 'band' => $band];
                }
            }
        }

        return self::$index = $index;
    }
}
