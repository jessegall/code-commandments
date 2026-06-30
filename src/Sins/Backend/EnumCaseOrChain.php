<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\EnumsWithBehaviour;

final class EnumCaseOrChain extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'enum-case-or-chain',
            skill: EnumsWithBehaviour::class,
            description: "`\$x === Enum::A || \$x === Enum::B` — a hand-rolled case-group test",
            rule: "Put case-group membership on the enum (a method); don't hand-roll `\$x === Enum::A || \$x === Enum::B`.",
            suggestion: "A membership method on the enum (`\$x->isFinal()`)."
        );
    }
}
