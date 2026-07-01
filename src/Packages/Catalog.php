<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Packages;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Discovery;

/**
 * Every {@see Package} that ships, discovered from the `Packages/` folder — the package twin of
 * the sin/detector/skill catalogs. The single place a general detector asks the registered
 * packages for cross-cutting facts, so the fact lives with the package that owns it, not scattered
 * as a literal across the detectors that must honour it.
 */
final class Catalog
{
    /**
     * @return list<Package>
     */
    public static function all(): array
    {
        $packages = [];

        foreach (Discovery::classes(__DIR__, __NAMESPACE__, 'Package') as $class) {
            if (is_subclass_of($class, Package::class)) {
                $packages[] = new $class;
            }
        }

        return $packages;
    }

    /**
     * The framework entry-point base types every registered package declares — the boundary a
     * structural rule exempts, aggregated so no detector names a framework itself.
     *
     * @return list<class-string>
     */
    public static function boundaryTypes(): array
    {
        return array_merge([], ...array_map(static fn (Package $package): array => $package->boundaryTypes(), self::all()));
    }

    /**
     * The framework contract methods every registered package declares — base class => the methods
     * whose shape/return the framework mandates — merged so a structural rule reads one map.
     *
     * @return array<class-string, list<string>>
     */
    public static function contractMethods(): array
    {
        $merged = [];

        foreach (self::all() as $package) {
            foreach ($package->contractMethods() as $base => $methods) {
                $merged[$base] = array_values(array_unique([...($merged[$base] ?? []), ...$methods]));
            }
        }

        return $merged;
    }

    /**
     * Is ($class, $method) a framework contract method — a mandated hook on a base $class extends?
     * The shared query the exempting detectors (near-duplicate, array-return-bag) compose, so the
     * "which base, which method" fact lives here, not copied into each.
     */
    public static function isContractMethod(Codebase $codebase, ?string $class, ?string $method): bool
    {
        if ($class === null || $method === null) {
            return false;
        }

        foreach (self::contractMethods() as $base => $methods) {
            if (in_array($method, $methods, true) && $codebase->extends($class, $base)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The framework config base types every registered package declares — classes whose array
     * returns are the framework's contract wholesale.
     *
     * @return list<class-string>
     */
    public static function arrayReturningTypes(): array
    {
        return array_merge([], ...array_map(static fn (Package $package): array => $package->arrayReturningTypes(), self::all()));
    }

    /**
     * Is $class a framework config type — one whose array returns are contractual, so a structural
     * rule leaves the whole class alone? The class-level twin of {@see isContractMethod}.
     */
    public static function returnsArraysByContract(Codebase $codebase, ?string $class): bool
    {
        foreach (self::arrayReturningTypes() as $base) {
            if ($codebase->extends($class, $base)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The framework no-container base types/contracts every registered package declares — bases the
     * framework instantiates itself, no DI.
     *
     * @return list<class-string>
     */
    public static function noContainerTypes(): array
    {
        return array_merge([], ...array_map(static fn (Package $package): array => $package->noContainerTypes(), self::all()));
    }

    /**
     * Is $class constructed by a framework WITHOUT the container — an Eloquent cast and the like —
     * so it has no constructor to inject into and a raw array/primitive parameter is the framework's
     * convention? Matched by the base it extends OR the contract it implements.
     */
    public static function isConstructedWithoutContainer(Codebase $codebase, ?string $class): bool
    {
        foreach (self::noContainerTypes() as $type) {
            if ($codebase->extends($class, $type) || $codebase->implements($class, $type)) {
                return true;
            }
        }

        return false;
    }
}
