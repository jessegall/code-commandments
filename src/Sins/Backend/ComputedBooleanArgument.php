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
            description: "a bool-only chooser whose callers all compute the flag off the same object (take the object and ask it)",
            rule: "Take the SUBJECT and ask it — never a bool every caller derives from that same object.",
            suggestion: "swap the flags for the object the callers already hold: `CornerInset::for(\$editor)`",
        );
    }
}
