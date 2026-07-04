<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend\Spatie;

use JesseGall\CodeCommandments\Sins\RequiresComposerPackage;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Spatie\PageObjects;

final class ServiceLocationInPageObject extends Sin implements RequiresComposerPackage
{
    use RequiresSpatieData;

    public function __construct()
    {
        parent::__construct(
            name: 'service-location-in-page-object',
            skill: PageObjects::class,
            description: "A page object reaches into the container with `app()`/`resolve()` instead of injecting the collaborator via `#[FromContainer]`",
            rule: "A page object pulls every collaborator through `#[FromContainer]` (hidden), never `app()`/`resolve()` inside a getter.",
            suggestion: "Inject it as a `#[Hidden] #[FromContainer(Service::class)]` constructor property."
        );
    }
}
