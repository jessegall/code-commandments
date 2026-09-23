<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\CSharp;

use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class Exceptions extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'csharp/exceptions',
            tier: Tier::KeepInMind,
            order: 31,
        );
    }

    public function title(): string
    {
        return 'C# exceptions — fail loud, named, at the source';
    }

    public function trigger(): string
    {
        return "Writing a `throw`, a `try`/`catch`, or an exception class in C# — or deciding what a method does when something goes wrong. Read this BEFORE you write an empty `catch`, a `catch` that returns `null`/`default`/an empty list, `throw new InvalidOperationException(\"…\")` with a message built at the throw, or a rethrow that drops the original exception.";
    }

    public function intro(): string
    {
        return "**Fail hard, fix once** beats *fail gracefully, debug forever.* A loud, named, contextual
exception thrown where the problem is born tells the next reader exactly what broke and why; a
swallowed one hands them a wrong value three calls later and no idea where it came from.";
    }

    public function summary(): string
    {
        return 'throw named exceptions built by a static factory, never swallow a failure, and keep the cause as the inner exception.';
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
A failure is information. The instant it happens it knows the most it will ever know — *what*
broke and *with what values*. Throw that knowledge **loudly, by type, at the source**. Every line
between the failure and its surfacing — a `catch` that returns `null`, a default that papers over
it, a bare `new Exception("...")` — destroys information and moves the debugging session further
from the cause.

### Never swallow a failure

`catch { }`, `catch (Exception) { return null; }`, `catch (IOException) { return []; }` turn a
failure into a value the caller cannot tell from a real answer. If the failure is expected and has
a meaning, catch the **specific** exception and do the thing that meaning calls for; if it is not,
let it propagate.

### Name the failure, and let its class write the message

A framework exception thrown with a message — `throw new InvalidOperationException($"unknown
carrier {name}")` — says nothing a caller can catch by meaning, and every throw site re-words the
same failure. Give the failure a class of its own, and give that class a **static factory** that
takes the values and writes the message once:

```csharp
public sealed class UnknownCarrier : InvalidOperationException
{
    private UnknownCarrier(string message) : base(message) {}

    public static UnknownCarrier Named(string name) => new($"No carrier is registered as '{name}'.");
}

throw UnknownCarrier.Named(name);
```

### Keep the cause

A `throw` inside a `catch` that translates one failure into another passes the original on as the
inner exception: `throw ImportFailed.Of(path, error);`, the factory handing `error` to the base
constructor. To rethrow the same exception, write `throw;` — `throw error;` restarts its stack trace
at the rethrow and loses where it really happened.

### The one place you tolerate: a named outer boundary

Fail-hard does **not** mean every layer rethrows forever. Failures travel *up* to **one explicit
boundary** allowed to absorb them — an ASP.NET Core exception handler, a `BackgroundService` loop, a
decoder of untrusted input — and even there absorbing is **observable**: it logs, counts or
reports, never silently continues.

### What is NOT this sin

- The framework's own guard helpers — `ArgumentNullException.ThrowIfNull(order)`,
  `ArgumentOutOfRangeException.ThrowIfNegative(count)` — are the idiom for a bad argument.
- An exception filter — `catch (HttpRequestException error) when (error.StatusCode == NotFound)` —
  catching the one failure that has a meaning here.
PRINCIPLE;
    }

    public function related(): array
    {
        return [
            \JesseGall\CodeCommandments\Skills\Backend\Exceptions::class => 'the same discipline on the PHP backend, with `::for()` factories.',
            \JesseGall\CodeCommandments\Skills\Python\Exceptions::class => 'the same discipline in Python, with classmethod factories.',
            \JesseGall\CodeCommandments\Skills\Backend\Absence::class => 'whether "missing" is a failure to throw at all, or a value to model.',
        ];
    }

    public function languages(): array
    {
        return [Language::CSharp];
    }
}
