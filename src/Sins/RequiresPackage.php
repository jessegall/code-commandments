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
interface RequiresPackage {}
