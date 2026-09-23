<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\Exceptions;

final class SwallowedException extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-swallowed-exception',
            skill: Exceptions::class,
            description: 'A bare `except:` or `except Exception` whose body only passes, continues or returns nothing — every failure, expected or not, made to vanish',
            rule: 'Never swallow every failure: catch the one you expect and act on it, or let it propagate to a boundary that records it.',
            suggestion: 'Name the exception you expect (`except ValueError:`) and do what its meaning calls for; anything else propagates. At a real boundary, log or report before moving on.',
        );
    }
}
