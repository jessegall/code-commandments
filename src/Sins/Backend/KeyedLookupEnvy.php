<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\TellDontAsk;

final class KeyedLookupEnvy extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'keyed-lookup-envy',
            skill: TellDontAsk::class,
            description: "Indirect feature envy — a method that uses an owned object's IDENTITY as a key to look up a fact about it through a collaborator"
        );
    }
}
