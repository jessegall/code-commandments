<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend\Laravel;

use JesseGall\CodeCommandments\Sins\RequiresComposerPackage;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Laravel\LaravelIdioms;

final class ModelMutationAtCallSite extends Sin implements RequiresComposerPackage
{
    use RequiresLaravel;

    public function __construct()
    {
        parent::__construct(
            name: 'model-mutation-at-call-site',
            skill: LaravelIdioms::class,
            description: "Set-property-then-`save()` at a call site (should be an intention method)",
            rule: "Mutate a model through an intention method; don't set-property-then-`save()` at a call site.",
            suggestion: "An intention method on the model (`\$order->suspend(\$reason)`)."
        );
    }
}
