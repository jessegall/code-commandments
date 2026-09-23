<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\Python;

use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class Exceptions extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'python/exceptions',
            tier: Tier::KeepInMind,
            order: 30,
        );
    }

    public function title(): string
    {
        return 'Python exceptions — fail loud, named, at the source';
    }

    public function trigger(): string
    {
        return "Writing a `raise`, a `try`/`except`, or an exception class in Python — or deciding what a function does when something goes wrong. Read this BEFORE you write `except Exception: pass`, an `except` that returns `None`/`[]`/`False`, `raise ValueError(\"…\")` with a message built at the raise, or a `raise` inside an `except` without `from`.";
    }

    public function intro(): string
    {
        return "**Fail hard, fix once** beats *fail gracefully, debug forever.* A loud, named, contextual
failure is a five-minute fix. A swallowed one is a silent wrong result you chase for a week.";
    }

    public function summary(): string
    {
        return 'raise named exceptions built by a classmethod factory, never swallow a failure, and keep the cause with `raise … from`.';
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
A failure is information. The instant it happens it knows the most it will ever know — *what*
broke and *with what values*. Raise that knowledge **loudly, by type, at the source**. Every line
between the failure and its surfacing — an `except` that returns `None`, a default that papers
over it, a bare `ValueError("...")` — destroys information and moves the debugging session
further from the cause.

### Never swallow a failure

`except Exception: pass`, `except KeyError: return None`, `except OSError: return []` turn a
failure into a value the caller cannot tell from a real answer. If the failure is expected and
has a meaning, catch the **specific** exception and do the thing that meaning calls for; if it is
not, let it propagate.

### Name the failure, and let its class write the message

A bare builtin raised with a message — `raise ValueError(f"unknown carrier {name}")` — says
nothing a caller can catch by meaning, and every raise site re-words the same failure. Give the
failure a class of its own, and give that class a **classmethod factory** that takes the values
and writes the message once:

```python
class UnknownCarrier(LookupError):
    @classmethod
    def named(cls, name: str) -> "UnknownCarrier":
        return cls(f"no carrier is registered as {name!r}")


raise UnknownCarrier.named(name)
```

### Keep the cause

A `raise` inside an `except` that translates one failure into another says where it came from:
`raise ImportFailed.of(path) from error`. Without `from`, the original is reported as an
accident that happened while handling it.

### The one place you tolerate: a named outer boundary

Fail-hard does **not** mean every layer re-raises forever. Failures travel *up* to **one explicit
boundary** allowed to absorb them — a request handler, a worker loop, a decoder of untrusted
input — and even there absorbing is **observable**: it logs, counts or reports, never silently
continues.
PRINCIPLE;
    }

    public function related(): array
    {
        return [
            \JesseGall\CodeCommandments\Skills\Backend\Exceptions::class => 'the same discipline on the PHP backend, with `::for()` factories.',
            \JesseGall\CodeCommandments\Skills\Backend\Absence::class => 'whether "missing" is a failure to raise at all, or a value to model.',
        ];
    }

    public function languages(): array
    {
        return [Language::Python];
    }
}
