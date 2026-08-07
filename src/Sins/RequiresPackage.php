<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins;

/**
 * A sin that only exists because a project installs a specific third-party package. The
 * base of the two ecosystem-specific contracts — {@see RequiresComposerPackage} (a
 * Composer `vendor/name`) and {@see RequiresNpmPackage} (an npm package) — so code that
 * only asks "is this rule bound to a package at all?" tests `instanceof RequiresPackage`,
 * while the filter reads the concrete ecosystem to pick the right manifest.
 *
 * The ecosystem is stated by the concrete interface, NOT inferred from the rule's
 * engine: a FRONTEND sin may require a Composer package (a hand-copied server type is a
 * frontend sin, yet `spatie/laravel-typescript-transformer` — a Composer package — is
 * what makes the fix possible).
 */
interface RequiresPackage
{
    /**
     * The package this sin needs, named as its own ecosystem names it — a Composer `vendor/name`,
     * an npm package. The sin is filtered out (it never runs and never shows up) when that package
     * is not installed in the project being judged.
     */
    public function requiredPackage(): string;

    /**
     * WHICH ecosystem's manifest answers that — `composer` or `npm`. Asked of the sin rather than
     * read off its type, and never inferred from the rule's engine: a FRONTEND sin may require a
     * Composer package, because it is `spatie/laravel-typescript-transformer` that makes the fix
     * possible even though the sin is in a `.vue` file.
     */
    public function ecosystem(): string;
}
