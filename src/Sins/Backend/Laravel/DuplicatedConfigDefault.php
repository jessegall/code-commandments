<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend\Laravel;

use JesseGall\CodeCommandments\Sins\RequiresComposerPackage;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Laravel\LaravelIdioms;

final class DuplicatedConfigDefault extends Sin implements RequiresComposerPackage
{
    use RequiresLaravel;

    public function __construct()
    {
        parent::__construct(
            name: 'duplicated-config-default',
            skill: LaravelIdioms::class,
            description: 'A config key whose default is stated TWICE — once in the config file, again as the reader\'s inline fallback — two sources of truth that drift silently',
            rule: 'The config FILE owns the default. A reader asks for the value; it does not restate what the value should be when absent.',
            suggestion: 'Drop the reader\'s fallback and let the config file answer — or delete the key from the file and let the reader\'s default be the one truth.'
        );
    }
}
