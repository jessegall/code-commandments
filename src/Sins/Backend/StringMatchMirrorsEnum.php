<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\EnumsWithBehaviour;

final class StringMatchMirrorsEnum extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'string-match-mirrors-enum',
            skill: EnumsWithBehaviour::class,
            description: "`match`/`switch` over string/int literals that mirror an existing backed enum's case values",
            rule: "Dispatch over the enum's cases, not string/int literals that mirror its values.",
            suggestion: "Dispatch via a method on the backed enum's cases."
        );
    }
}
