<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Absence;

final class ErasedNullObject extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'erased-null-object',
            skill: Absence::class,
            description: "A blank-rendering Null Object written into a `string` slot — coerced back to `''`",
            rule: "A Null Object only models absence while the TYPE admits it; never hand one to a `string`-typed slot, which coerces it to `''` and erases it.",
            suggestion: "Widen the type to carry the object (`Stringable`, or the class itself) where the Null Object is the point; otherwise drop the wrapper — and where the blank meant \"missing\", say that in the type with `?string` or an `Option<string>`.",
        );
    }
}
