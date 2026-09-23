<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\PassTheObject;

final class ComputedBooleanArgument extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'computed-boolean-argument',
            skill: PassTheObject::class,
            description: 'A parameter that\'s just true/false, computed by every caller from the same object it could be given instead.',
            rule: "Take the SUBJECT and ask it — never a bool every caller derives from that same object.",
            suggestion: "swap the flags for the object the callers already hold: `CornerInset::for(\$editor)`",
        );
    }
}
