<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend\Laravel;

use JesseGall\CodeCommandments\Sins\RequiresComposerPackage;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Laravel\LaravelIdioms;

final class MassUpdateAtCallSite extends Sin implements RequiresComposerPackage
{
    use RequiresLaravel;

    public function __construct()
    {
        parent::__construct(
            name: 'mass-update-at-call-site',
            skill: LaravelIdioms::class,
            description: "Bare `\$model->update([...])` mass-array update at a call site",
            rule: "Mutate a model through an intention method; never `\$model->update([...])` an anonymous array of columns at a call site.",
            suggestion: "An intention method on the model (`\$order->markPaid()`)."
        );
    }
}
