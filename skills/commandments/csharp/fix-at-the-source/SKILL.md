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

- [ ] A constructor sets up the object; creating one should never change anything outside it.
      _Keep the collaborator in a field and call it from the method someone actually calls to do the work._

## Worked example

### csharp-constructor-side-effect

a constructor that calls a method on something it was handed and ignores the result — just creating the object changes something outside it

```cs
----------[ Bad ]----------

public NewsletterPreference(string address, MailingList list)
{
    this.address = address;

    list.Join(address);
}

----------[ Good ]----------

public sealed class NewsletterSignup(string address, MailingList list)
{
    public void Confirm() => list.Join(address);
}
```

## Commands

- `vendor/bin/commandments judge --skill=csharp/fix-at-the-source` — find every one of these in the codebase.
- `vendor/bin/commandments info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `csharp-constructor-side-effect`.
- `vendor/bin/commandments report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`backend/fix-at-the-source`](../../backend/fix-at-the-source/SKILL.md) — the same discipline in PHP, which every other skill builds on.
- [`csharp/absence`](../absence/SKILL.md) — deciding what "missing" means where a value is made is this rule applied to `null`.
