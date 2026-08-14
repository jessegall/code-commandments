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
            description: "A parameter declared in the wrong currency — call site after call site wraps the same argument in the same conversion (`Raises::of(ClassAlias::of(\$interaction), …)`) because the callee asks for the converted form instead of the value",
            rule: "Declare the parameter in the currency callers actually hold, and convert inside — one rule about the conversion, in one place.",
            suggestion: "Move the wrapper into the callee and widen the parameter to the type being wrapped; every call site then passes the value it means, and a site that forgets the conversion stops compiling."
        );
    }
}
