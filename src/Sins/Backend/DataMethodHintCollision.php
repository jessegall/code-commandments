<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\SpatieData;

final class DataMethodHintCollision extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'data-method-hint-collision',
            skill: SpatieData::class,
            description: "`@method` tag that re-declares a real method (names the concrete factory, not the magic `from`/`collect`)"
        );
    }
}
