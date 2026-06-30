<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Absence;

final class DeNulledFinder extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'de-nulled-finder',
            skill: Absence::class,
            description: "Missing = broken state returned as `?T`/null instead of throwing (a `?T` finder whose callers de-null it)"
        );
    }
}
