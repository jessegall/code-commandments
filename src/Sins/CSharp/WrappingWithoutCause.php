<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\Exceptions;

final class WrappingWithoutCause extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-wrapping-without-cause',
            skill: Exceptions::class,
            description: 'a `catch` that throws a new exception without passing the caught one as its inner exception, so the original stack trace is lost',
            rule: 'When you wrap a caught exception, pass it on as the inner exception; never throw away the original.',
            suggestion: 'Catch it into a variable and pass it on — `catch (IOException e) { throw new LoadFailed("…", e); }` — or rethrow with `throw;`.',
        );
    }
}
