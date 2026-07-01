<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins;

/**
 * A sin that only exists because of a specific third-party package declares that package by
 * NAME — a Composer package (`spatie/laravel-data`) for a BACKEND rule, an npm package
 * (`@vueuse/core`) for a FRONTEND one. At CLI runtime, a rule whose package isn't present in the
 * project is filtered out entirely: it never runs, never shows in `--list`, and can't be
 * reported. (Such a rule was already inert — nothing to match — but this makes it explicit and
 * skips the scan.)
 *
 * The ecosystem is picked by the rule's engine, so the name stays a plain string: a backend
 * rule is checked against Composer's installed set ({@see \Composer\InstalledVersions}), a
 * frontend rule against the project's `package.json`. Both are read from the project the CLI
 * runs in, and both fall back to "present" when the manifest can't be read, so an unknown
 * environment never over-filters. Filtering happens only at runtime (judge / repent / list); the
 * shipped {@see Catalog} and the generated docs still list everything, so the package's own
 * fixtures and README stay complete.
 */
interface RequiresPackage
{
    /**
     * The package this sin needs — a Composer `vendor/name` for a backend sin, an npm package
     * name for a frontend sin. The rule is kept only when that package is present in the project
     * being judged.
     */
    public function requiredPackage(): string;
}
