<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use Composer\InstalledVersions;

/**
 * WHICH version of a package the project being judged has. A generated helper extends and overrides
 * the vendor's classes, and a major that renamed them turns the same scaffold from a fix into a fatal
 * — so the answer has to come from the project's own install set, never from a guess.
 */
final class InstalledPackage
{
    /**
     * The installed MAJOR of $package, or null when it is absent or its version cannot be read —
     * which is the honest answer for a project we cannot inspect, and never a default major.
     */
    public static function majorOf(string $package): ?int
    {
        if (! class_exists(InstalledVersions::class) || ! InstalledVersions::isInstalled($package)) {
            return null;
        }

        $version = ltrim((string) InstalledVersions::getPrettyVersion($package), 'v');
        $major = strtok($version, '.');

        return $major !== false && is_numeric($major) ? (int) $major : null;
    }
}
