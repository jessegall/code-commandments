<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend\Laravel;

/**
 * Shared {@see \JesseGall\CodeCommandments\Sins\RequiresComposerPackage} answer for the laravel-idioms
 * sins: facades, Eloquent mutation, the `config()`/`app()` helpers and typed requests come from
 * Laravel's `illuminate/*` components. Keyed on `illuminate/support` — the foundational base every
 * component (and the full `laravel/framework`) depends on — so the rules apply to a full Laravel
 * app AND a project using illuminate parts standalone.
 */
trait RequiresLaravel
{
    public function requiredComposerPackage(): string
    {
        return 'illuminate/support';
    }
}
