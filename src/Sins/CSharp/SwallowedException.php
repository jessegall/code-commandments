<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\Exceptions;

final class SwallowedException extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-swallowed-exception',
            skill: Exceptions::class,
            description: 'A bare `catch` or `catch (Exception)` whose body is empty, continues, or returns nothing — every failure, expected or not, made to vanish',
            rule: 'Never swallow every failure: catch the one you expect and act on it, or let it propagate to a boundary that records it.',
            suggestion: 'Name the exception you expect (`catch (FileNotFoundException)`), or filter it with `when`, and do what its meaning calls for; anything else propagates. At a real boundary, log or report before moving on.',
        );
    }
}
