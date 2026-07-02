<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins;

/**
 * A sin that only exists because a project installs a specific COMPOSER package —
 * `spatie/laravel-data`, `laravel/framework`, `spatie/laravel-typescript-transformer`.
 * At CLI runtime a rule whose package isn't in the project's Composer install set
 * ({@see \Composer\InstalledVersions}) is filtered out entirely: it never runs, never
 * shows in `--list`, and can't be reported. (It was already inert — nothing to match —
 * this makes it explicit and skips the scan.)
 *
 * The ecosystem is stated by the interface, NOT inferred from the rule's engine: a
 * FRONTEND sin may require a Composer package (a hand-copied server type is a frontend
 * sin, but it is `spatie/laravel-typescript-transformer` that makes the fix possible).
 * The npm counterpart is {@see RequiresNpmPackage}; both extend {@see RequiresPackage}.
 * The check falls back to "present" when the manifest can't be read, so an unknown
 * environment never over-filters.
 */
interface RequiresComposerPackage extends RequiresPackage
{
    /**
     * The Composer `vendor/name` this sin needs. The sin is filtered out — it never runs
     * and never shows up — when that package isn't installed in the project being judged.
     */
    public function requiredComposerPackage(): string;
}
