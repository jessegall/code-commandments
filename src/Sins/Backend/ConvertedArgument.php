<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\PassTheObject;

final class ConvertedArgument extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'converted-argument',
            skill: PassTheObject::class,
            description: 'A parameter typed as the already-converted form instead of the raw value, so every call site repeats the same conversion before calling it (e.g. `Raises::of(ClassAlias::of($interaction), …)`).',
            rule: 'Declare the parameter in the form callers already have, and do the conversion inside the callee — so the conversion rule lives in one place.',
            suggestion: "Move the wrapper into the callee and widen the parameter to the type being wrapped; every call site then passes the value it means, and a site that forgets the conversion stops compiling."
        );
    }
}
