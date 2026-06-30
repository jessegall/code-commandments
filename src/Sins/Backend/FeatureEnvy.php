<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\TellDontAsk;

final class FeatureEnvy extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'feature-envy',
            skill: TellDontAsk::class,
            description: "Exiled behaviour / feature envy — a method operating on ONE other owned object's internals that belongs ON that object"
        );
    }
}
