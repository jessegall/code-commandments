---
name: commandments-csharp-exceptions
description: "Writing a `throw`, a `try`/`catch`, or an exception class in C# — or deciding what a method does when something goes wrong. Read this BEFORE you write an empty `catch`, a `catch` that returns `null`/`default`/an empty list, `throw new InvalidOperationException(\"…\")` with a message built at the throw, or a rethrow that drops the original exception."
---

# C# exceptions — fail loud, named, at the source

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> **Fail hard, fix once** beats *fail gracefully, debug forever.* A loud, named, contextual
> exception thrown where the problem is born tells the next reader exactly what broke and why; a
> swallowed one hands them a wrong value three calls later and no idea where it came from.

## The principle

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

## Rules

- [ ] Throw a named exception built by a static factory, never a bare `Exception` or `InvalidOperationException` with a message written at the throw.
      _Give the failure a class of its own with a static factory that takes the values and writes the message once — `throw UnknownCarrier.Named(name);` — so a caller can catch it by name._
- [ ] Never swallow every failure: catch the one you expect and act on it, or let it propagate to a boundary that records it.
      _Name the exception you expect (`catch (FileNotFoundException)`), or filter it with `when`, and do what its meaning calls for; anything else propagates. At a real boundary, log or report before moving on._

## Worked example

### csharp-generic-throw

`throw new Exception/InvalidOperationException("…")` — a failure that names nothing, described in prose at the throw site

```cs
----------[ Bad ]----------

public string Account(string carrier)
{
    if (!accounts.TryGetValue(carrier, out var account))
    {
        throw new Exception($"No carrier is registered as '{carrier}'.");
    }

    return account;
}

----------[ Good ]----------

// in CarrierDirectory.cs
public string AccountOf(string carrier)
{
    if (!accounts.TryGetValue(carrier, out var account))
    {
        throw UnknownCarrier.Named(carrier);
    }

    return account;
}

// in CarrierDirectory.cs
public sealed class UnknownCarrier : InvalidOperationException
{
    private UnknownCarrier(string message) : base(message) {}

    public static UnknownCarrier Named(string carrier) => new($"No carrier is registered as '{carrier}'.");
}
```

The other 1 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `vendor/bin/commandments judge --skill=csharp/exceptions` — find every one of these in the codebase.
- `vendor/bin/commandments info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `csharp-generic-throw`, `csharp-swallowed-exception`.
- `vendor/bin/commandments report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 2 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`backend/exceptions`](../../backend/exceptions/SKILL.md) — the same discipline on the PHP backend, with `::for()` factories.
- [`python/exceptions`](../../python/exceptions/SKILL.md) — the same discipline in Python, with classmethod factories.
- [`backend/absence`](../../backend/absence/SKILL.md) — whether "missing" is a failure to throw at all, or a value to model.
