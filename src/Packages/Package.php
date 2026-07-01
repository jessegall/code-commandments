<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Packages;

/**
 * One third-party package's cross-cutting policy — the extension point where a package declares
 * facts that OTHER detectors must respect, so a general rule never hard-codes a framework's types.
 * Each package is its own class under `Packages/`, discovered by {@see Catalog} the way sins,
 * detectors, and skills are; a consumer's own `Packages\` class auto-enrols to teach the engine
 * about a framework the package doesn't ship for.
 *
 * A package registers EXEMPTIONS against tags — see {@see register}. The mechanism is open: a
 * detector reads a tag ({@see Exemptions::has}), a package registers against it, and neither names
 * the other.
 */
abstract class Package
{
    /**
     * Register this package's exemptions. Each is keyed by a TAG (a class-string both the detector
     * and this package agree on — a built-in {@see Tags} marker, or a custom detector's own class):
     *
     *   $ex->exempt(Boundary::class)->classes(Request::class, FormRequest::class);
     *   $ex->exempt(ContractMethod::class)->on(FormRequest::class, 'rules')->on(Model::class, 'casts');
     *
     * A detector then asks `Exemptions::has($tag, $codebase, $class[, $method])` and leaves the
     * match alone — without ever naming this framework.
     */
    public function register(Exemptions $exemptions): void
    {
    }
}
