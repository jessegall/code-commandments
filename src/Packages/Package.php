<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Packages;

/**
 * Extension point where packages register facts other detectors must respect. Each package
 * under `Packages/` auto-enrolls via {@see Catalog}, and its {@see register} method exempts
 * framework-specific types against tags so detectors stay generic.
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
