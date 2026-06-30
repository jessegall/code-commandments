<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\EnumsWithBehaviour;

final class MatchDefaultReturnsNull extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'match-default-returns-null',
            skill: EnumsWithBehaviour::class,
            description: "`match` `default` that returns `null`/`''`/`[]` instead of throwing"
        );
    }
}
