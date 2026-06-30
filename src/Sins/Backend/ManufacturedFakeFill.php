<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\FixAtTheSource;

final class ManufacturedFakeFill extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'manufactured-fake-fill',
            skill: FixAtTheSource::class,
            description: "`?? <empty literal>` filling a required slot (manufactured fake)"
        );
    }
}
