<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend\Spatie;

use JesseGall\CodeCommandments\Sins\RequiresComposerPackage;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Spatie\PageObjects;

final class PageObjectMissingTypeScript extends Sin implements RequiresComposerPackage
{
    use RequiresSpatieData;

    public function __construct()
    {
        parent::__construct(
            name: 'page-object-missing-typescript',
            skill: PageObjects::class,
            description: "A page object travels back in a response but carries no `#[TypeScript]` — the `.vue` page reads it as untyped `any`, so the whole page-prop contract goes unchecked",
            rule: "Annotate every page object `#[TypeScript]` so it generates a frontend type the page binds against — a response-bound Data with no annotation is a type-safety hole.",
            suggestion: "Add `#[TypeScript]` above the page-object class (`use Spatie\\TypeScriptTransformer\\Attributes\\TypeScript;`).",
        );
    }
}
