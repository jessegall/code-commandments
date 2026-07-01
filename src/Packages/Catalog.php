<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Packages;

use JesseGall\CodeCommandments\Discovery;

/**
 * Every {@see Package} that ships, discovered from the `Packages/` folder — the package twin of
 * the sin/detector/skill catalogs. The cross-detector facts a package registers are read through
 * {@see Exemptions}; this is just the roster it builds from.
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
}
