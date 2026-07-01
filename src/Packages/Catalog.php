<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Packages;

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
        return array_merge(...array_map(static fn (Package $package): array => $package->boundaryTypes(), self::all()));
    }
}
