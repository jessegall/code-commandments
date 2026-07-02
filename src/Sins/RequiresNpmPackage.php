<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins;

/**
 * A sin that only exists because a project installs a specific NPM package —
 * `@vueuse/core`, say. At CLI runtime a rule whose package isn't in the project's
 * `package.json` (dependencies or devDependencies) is filtered out entirely: it never
 * runs, never shows in `--list`, and can't be reported.
 *
 * The ecosystem is stated by the interface, not inferred from the rule's engine — the
 * Composer counterpart is {@see RequiresComposerPackage}; both extend {@see RequiresPackage}.
 * The check falls back to "present" when the manifest can't be read, so an unknown
 * environment never over-filters.
 */
interface RequiresNpmPackage extends RequiresPackage
{
    /**
     * The npm package name this sin needs. The sin is filtered out — it never runs and
     * never shows up — when that package isn't a dependency of the project being judged.
     */
    public function requiredNpmPackage(): string;
}
