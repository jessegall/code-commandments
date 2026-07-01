<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

/**
 * Shared {@see \JesseGall\CodeCommandments\Sins\RequiresPackage} answer for the laravel-idioms
 * sins: facades, Eloquent mutation, the `config()`/`app()` helpers and typed requests only exist
 * in a Laravel project, so these rules apply only where `laravel/framework` is installed.
 */
trait RequiresLaravel
{
    public function requiredPackage(): string
    {
        return 'laravel/framework';
    }
}
