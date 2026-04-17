<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use Composer\InstalledVersions;

/**
 * Resolves the currently-installed package version and the previously
 * synced version (persisted in `<base-path>/.commandments-last-synced`).
 *
 * `sync --after=previous` uses these to answer "show me prophets added
 * since the last time I synced" without the user having to remember
 * their old version.
 */
class VersionResolver
{
    public const PACKAGE_NAME = 'jessegall/code-commandments';

    public const STATE_FILENAME = '.commandments-last-synced';

    /**
     * Currently-installed version from Composer metadata. Returns null for
     * dev installs (e.g. `dev-main`) or when Composer's metadata isn't
     * available — callers should skip version-aware behavior in that case.
     */
    public function currentVersion(): ?string
    {
        if (! class_exists(InstalledVersions::class)) {
            return null;
        }

        try {
            $version = InstalledVersions::getPrettyVersion(self::PACKAGE_NAME);
        } catch (\OutOfBoundsException) {
            return null;
        }

        if ($version === null) {
            return null;
        }

        return $this->normalizeSemver($version);
    }

    public function previousSyncedVersion(string $basePath): ?string
    {
        $path = $this->stateFilePath($basePath);

        if (! is_file($path)) {
            return null;
        }

        $contents = @file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        $version = trim($contents);

        return $this->normalizeSemver($version);
    }

    public function recordSyncedVersion(string $basePath, string $version): bool
    {
        $path = $this->stateFilePath($basePath);

        return @file_put_contents($path, $version . "\n") !== false;
    }

    public function stateFilePath(string $basePath): string
    {
        return rtrim($basePath, '/') . '/' . self::STATE_FILENAME;
    }

    /**
     * Strip a leading `v` and reject dev refs that can't be compared via
     * `version_compare`.
     */
    private function normalizeSemver(string $version): ?string
    {
        $version = ltrim($version, 'v');

        if ($version === '' || str_starts_with($version, 'dev-') || str_contains($version, '@')) {
            return null;
        }

        if (preg_match('/^\d+(\.\d+){0,2}(?:-[0-9A-Za-z-.]+)?$/', $version) !== 1) {
            return null;
        }

        return $version;
    }
}
