<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend\Spatie;

/**
 * Shared {@see \JesseGall\CodeCommandments\Sins\RequiresComposerPackage} answer for the spatie-data
 * sins: they only make sense in a project that installs `spatie/laravel-data`.
 */
trait RequiresSpatieData
{
    public function requiredComposerPackage(): string
    {
        return 'spatie/laravel-data';
    }
}
