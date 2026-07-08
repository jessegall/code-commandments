<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Packages\Tags;

use JesseGall\CodeCommandments\Packages\Exemption;

/**
 * Exemption tag: a composition root — a service provider's `register()`/`boot()`, where the framework is
 * wired at boot and `config()` is converted into the typed objects it injects. A provider cannot
 * constructor-inject its own config, so reading `config(...)` here is its sanctioned job — exempt from config-read.
 */
final class CompositionRoot extends Exemption
{
    public function slug(): string
    {
        return 'composition-root';
    }

    public function description(): string
    {
        return 'A service provider\'s register/boot — the composition root where config() is wired into typed objects; a provider can\'t inject its own config, so config() reads here are exempt from config-read.';
    }
}
