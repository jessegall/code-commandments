---
name: exceptions
description: How to fail — throw NAMED exceptions via static factories (`Thing::for($x)`), never a message string at the throw site, and never swallow a failure into null/false/[]/Option::none(). Read this FIRST whenever you write a `throw`, a `try`/`catch`, an exception class, or are deciding what to do when something goes wrong. Fail hard and named, at the source.
---

# Exceptions — fail hard, fix once

> **Fail hard, fix once** beats *fail gracefully, debug forever.* A loud, named, contextual failure is a
> five-minute fix. A swallowed one is a silent wrong result you chase for a week.

## The principle

A failure is information. The instant it happens, it knows the most it will ever know — *what* broke and
*with what values*. Throw that knowledge **loudly, by type, at the source**. Every line you put between
the failure and its surfacing — a `catch` that returns null, a default that papers over it, a bare
`Exception("...")` — destroys information and moves the eventual debugging session further from the cause.

Two rules carry the whole skill:

1. **Throw named exceptions; the throw site passes domain values, never strings.**
2. **Never swallow a failure into an absence value.**

This is [`fix-at-the-source`](../fix-at-the-source/SKILL.md) for the error channel — and the place the
[`absence`](../absence/SKILL.md) skill sends you when "missing" turns out to be a broken state.

## Rule 1 — named exceptions via static factories

The throw site passes **domain values**. All message assembly lives **inside the exception**, in a static
factory (preferred) or its constructor. Then the failure can be caught by *type*, the message has one
home, and a second throw site can't duplicate-and-drift the string.

```php
// Bad — a generic exception, message assembled at the throw site
throw new RuntimeException("No agent model is configured for id '{$id}'.");

// Good — a named type; the call site passes data, the exception writes the sentence
final class UnknownAgentModelException extends RuntimeException
{
    public static function forId(string $id): self
    {
        return new self("No agent model is configured for id '{$id}'.");
    }
}

throw UnknownAgentModelException::forId($id);
```

- **`::for(...)` is the default factory name.** Purpose-named factories are better when one exception
  covers variants: `InvalidWorkflowGraphException::duplicateNode($id)`,
  `UnusableAssistantActionException::unknownType($type)`, `CannotCoerceValueException::toInt($value)`.
- **`::for*` returns `self` and is the ONLY place `new self("...")` with a message lives.**

**A factory does NOT launder a message.** Handing a sentence to `::make($x, 'must define mapTo()')` is the
same sin wearing a factory hat — the prose still lives at the call site. A multi-word string argument to a
factory is the tell. The factory takes domain values and writes the sentence itself.

```php
throw PortNotWiredException::for($name);           // righteous — factory builds the message
throw new PortNotWiredException($port, $node);      // acceptable — ctor builds the message
throw new PortNotWiredException("Port '...' ...");  // sin — message leaked to the throw site
throw PortNotWiredException::make("Port '...' ...");// sin — factory as message courier
```

## Rule 2 — never swallow a failure into an absence

A `catch` whose only effect is to return `null` / `false` / `[]` / `Option::none()` converts a *failure*
into a *value*, and the caller can't tell the difference. That's the worst kind of "graceful": the system
keeps running on a wrong result.

```php
// Bad — the failure becomes an empty result; the cause vanishes
try {
    return $this->client->fetch($id);
} catch (\Throwable) {
    return Option::none();
}

// Good — let it throw (fail hard), or surface it named with the cause attached
try {
    return $this->client->fetch($id);
} catch (TransportException $e) {
    throw ResourceFetchFailedException::forId($id, $e);   // pass the original as `previous`
}
```

- **No empty/no-op catch.** A catch that does nothing (or only a comment) hides a failure outright.
- **Bind and use the caught exception.** When you wrap, pass the original as `previous`/cause — never drop
  the stack trace.
- **Don't catch a broad `\Throwable`/`\Exception` to coerce one known failure into a sentinel.** Catch the
  narrow type, or don't catch at all.
- **"Missing must-exist thing" is not an absence — it throws.** `tryFrom()`/`find()` immediately
  `=== null` then `throw` means you wanted the total `from()`/`get()` that throws on miss.

## The one place you tolerate: a named outer boundary

Fail-hard does **not** mean every layer rethrows forever. It means failures travel *up* to **one explicit
boundary** that is allowed to absorb them — and even there, absorbing is **observable**, never silent. The
canonical shape: an untrusted-input decoder that catches per item, **logs**, and skips, then fails hard if
*nothing* survived.

```php
foreach ($entries as $entry)
{
    try
    {
        $decoded[] = $this->decodeAction($entry);   // inner code fails HARD
    }
    catch (\Throwable $e)
    {
        $this->logger->warning('Unusable action entry; skipped.', ['reason' => $e->getMessage()]);
        continue;                                    // tolerated HERE, and logged
    }
}

if ($decoded === [] && $entries !== [])
{
    throw MalformedAssistantActionsException::make();   // every entry failed → fail hard
}
```

Inside the system, invariants throw. At the *one* untrusted edge, you catch-log-skip. That's fail-hard
*and* resilient — not graceful-and-silent.

## Checklist

```
Exceptions
- [ ] Every throw passes domain VALUES; no message string at the throw site (no factory-as-courier either).
- [ ] The failure has a NAMED type that can be caught by type (not a bare SPL Exception/RuntimeException).
- [ ] No catch returns null/false/[]/Option::none() as its only effect; no empty catch.
- [ ] When wrapping, the original is passed as `previous`/cause.
- [ ] Failures are absorbed at ONE explicit boundary, and that absorption LOGS — it is never silent.
```

## Relationship to the other skills

- Parent move: [`fix-at-the-source`](../fix-at-the-source/SKILL.md) — surface the failure where it's born.
- [`absence`](../absence/SKILL.md) routes "missing = broken state" here for the *how* of throwing; this
  skill routes "swallowed failure became an empty value" back there as the inverse smell.
