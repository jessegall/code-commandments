<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend\Spatie;

use JesseGall\CodeCommandments\Sins\RequiresComposerPackage;
use JesseGall\CodeCommandments\Sins\Scaffold;
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
            suggestion: "Add `#[Hidden]` above the injection attribute — LaravelData's `#[Hidden]`, which keeps it off the wire. So one attribute ALSO keeps it out of the generated TypeScript, wire the scaffolded `HiddenAwareAttributedClassTransformer` into your typescript-transformer config; otherwise LaravelData's `#[Hidden]` alone still leaks the property into the TS type."
        );
    }

    /**
     * A LaravelData `#[Hidden]` drops a property from the wire but NOT from the generated TypeScript — the
     * transformer needs its OWN `#[Hidden]`. Rather than a second attribute on every injected service, we
     * scaffold a transformer that treats LaravelData-`#[Hidden]` as TS-hidden too, so one attribute covers
     * both. Registered by the author in the typescript-transformer config.
     */
    public function scaffolds(): array
    {
        return [
            new Scaffold('TypeScript/DropDataHiddenPropertyProcessor.php', 'DropDataHiddenPropertyProcessor.php.stub'),
            new Scaffold('TypeScript/HiddenAwareAttributedClassTransformer.php', 'HiddenAwareAttributedClassTransformer.php.stub'),
        ];
    }
}
