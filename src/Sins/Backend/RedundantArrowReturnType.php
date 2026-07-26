<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\TypeHonesty;

final class RedundantArrowReturnType extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'redundant-arrow-return-type',
            skill: TypeHonesty::class,
            description: 'An arrow function whose return type only repeats what its one expression provably yields — `fn (): string => $this->name` on a `string` property',
            rule: 'Leave the return type off an arrow function whose expression already proves the type. Declare one when the type is genuinely ambiguous or you are narrowing it — never to restate a property or a method you can read from here.',
            suggestion: 'drop the `: Type` — `repent` does this for you',
        );
    }
}
