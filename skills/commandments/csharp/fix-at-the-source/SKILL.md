---
name: commandments-csharp-fix-at-the-source
description: "Writing a C# constructor that calls a method on something it was handed, a `static` field that methods write to, or a fix for a C# finding that is tempting to patch where it showed up. Read this BEFORE making a constructor do work, keeping state in a static field, or adding a check at a call site, and when a `csharp-constructor-side-effect` or `csharp-mutable-static-state` finding points here."
---

# C# fix at the source — fix a value where it is made, not where it breaks

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> A wrong value is almost always wrong where it was made. The call site that fails is only where the
> problem showed up; adding a check there leaves the next caller to fail the same way. The same goes for
> objects and state in C#: creating an object shouldn't change anything outside it, and state that changes
> belongs on an instance someone owns, so every effect happens somewhere you can see.

## The principle

### Trace it back before you change a line

A finding is a symptom. Before adding an `is null` check, a `?? default` or a `try` where it showed up, ask
where the value came from and follow it back to the code that produced it. Fix that code, and the check
you were about to write — and every copy of it — is no longer needed.

### A constructor sets up; it doesn't act

A constructor says what the object is: it stores what it was given and works out what it needs. When it
calls a collaborator to do something — warm a cache, register itself, open a connection — and ignores
the result, just creating the object changes something outside it, in a line that looks like setup. Keep
the collaborator in a field and call it from the method someone calls, when they choose to.

```csharp
public sealed class Report(Printer printer)
{
    public void Start() => printer.Print("start");
}
```

### State that changes lives on an instance

A `static` field that methods write to is state every caller shares and nobody passes in. Who changed it,
and when, isn't written anywhere. Keep changing state on an instance, pass that instance to the code that
needs it (usually through the constructor), and the dependency is in the signature where a reader sees it.

## Rules

- [ ] Work in flight is awaited, never blocked on: `await` it, and make the caller async too, however far up that goes.
      _Write `await` and mark the method `async`, then follow the callers: the breadth of that change IS the problem surfacing, and stopping it with `.Result` at one boundary is what buries the deadlock until load finds it._
- [ ] A constructor sets up the object; creating one should never change anything outside it.
      _Keep the collaborator in a field and call it from the method someone actually calls to do the work._
- [ ] Keep state that changes on an instance someone owns and passes around; don't write to a static field from a method.
      _Move the field onto an object and hand that object to the code that reads and changes it; for a value computed once, use a `static readonly Lazy<T>`._
- [ ] A constructor asks for what the type uses, and nothing more: a collaborator nothing reads is removed, along with what the callers pass.
      _Delete the parameter and the field, then follow the callers: whatever they resolved or built only to hand it over goes too. If the type is meant to use it and does not, that is the bug the unread field is pointing at._

## Worked example

### csharp-blocked-await

`.Result`, `.Wait()` or `.GetAwaiter().GetResult()` on a task — the thread it blocks is the one the work needed back, so it deadlocks under load and nowhere else

```cs
----------[ Bad ]----------

public string Read(string supplier)
{
    return feed.ReadAsync(supplier).Result;
}

----------[ Good ]----------

public async Task<string> ReadAsync(string supplier)
{
    return await feed.ReadAsync(supplier);
}
```

The other 3 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `{{binary}} judge --skill=csharp/fix-at-the-source` — find every one of these in the codebase.
- `{{binary}} info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `csharp-blocked-await`, `csharp-constructor-side-effect`, `csharp-mutable-static-state`, `csharp-unread-collaborator`.
- `{{binary}} report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 4 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`backend/fix-at-the-source`](../../backend/fix-at-the-source/SKILL.md) — the same discipline in PHP, which every other skill builds on.
- [`csharp/absence`](../absence/SKILL.md) — deciding what "missing" means where a value is made is this rule applied to `null`.
