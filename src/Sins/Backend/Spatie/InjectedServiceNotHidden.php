<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend\Spatie;

use JesseGall\CodeCommandments\Sins\RequiresComposerPackage;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Spatie\PageObjects;

final class InjectedServiceNotHidden extends Sin implements RequiresComposerPackage
{
    use RequiresSpatieData;

    public function __construct()
    {
        parent::__construct(
            name: 'injected-service-not-hidden',
            skill: PageObjects::class,
            description: "A page object injects a service (`#[FromContainer]`, …) into a public property without `#[Hidden]` — it leaks into the generated TypeScript type",
            rule: "Every injected collaborator on a page object carries `#[Hidden]`, so the service never serializes or reaches the frontend type.",
            suggestion: "Add `#[Hidden]` above the injection attribute."
        );
    }
}
