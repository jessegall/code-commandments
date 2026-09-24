---
name: commandments-csharp-repeated-call-helper
description: "When you write the same thing the same way at site after site in C# — the same `with { Status = … }` copy, the same call with the same named argument, the same compound `if` condition, the same `x is A a && a.Inner is B` type check. Read this before copying a condition or a call from one method into another, and when a repeated-guard or repeated-call finding points here."
---

# C# repeated call helper — name what you keep writing

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> Something you write the same way over and over is a method you have not named yet. When the same call,
> the same condition or the same type check keeps coming back, give it a name — usually as a member of the
> type it is about — and let every site say what it means instead of how it is worked out.

## The principle

### The repeated copy or call

`order with { Status = OrderStatus.Shipped }` — a `with` expression is flexible, and at one site that
flexibility is the point. Written the same way at site after site, it is an operation the type should name:

```csharp
public sealed record Order(OrderStatus Status)
{
    public Order Shipped() => this with { Status = OrderStatus.Shipped };
}
```

Now every site says `order.Shipped()`. The same goes for a call that keeps passing the same named argument
(`Format(total, currency: "EUR")`): give that call a name. The general form stays for the real one-off.

### The repeated guard

The same compound condition — `if (order.IsPaid && !order.IsCancelled && order.Lines.Count > 0)` —
written in two places is a question with no name. Written with different spacing or in another order, it is
still the same question. Name it where the data lives:

```csharp
public bool IsShippable => IsPaid && !IsCancelled && Lines.Count > 0;
```

and every site asks `if (order.IsShippable)`. When the rule changes, it changes once.

### The repeated type check

`node is InvocationExpression call && call.Target is MemberAccess` copied from method to method checks a
shape that has no name. Give it one — a method or a property on the type — so the shape is declared once
and every site asks for it by name.

### When it is NOT this sin

- **A one-off.** A call or a condition written once is exactly what the general tool is for.
- **Really different arguments.** `order with { Status = … }` here and `order with { Note = … }` there are
  two operations, not one.
- **A simple check.** A single `if (x is null)` or one `is` test is not a compound condition; repeating it
  names nothing new.

## Rules

- [ ] Name a compound condition asked in more than one place once, on the type it is about, and ask it by that name.
      _Add `public bool IsShippable => Paid && !Cancelled;` to the type and write `if (order.IsShippable)` at every site._
- [ ] Name a copy written the same way at several sites as a method on the record, and call it by that name.
      _Add `public Order Shipped() => this with { Status = OrderStatus.Shipped };` to the record and write `order.Shipped()` at every site._

## Worked example

### csharp-repeated-guard

the same compound condition — `order.Paid && !order.Cancelled` — written at two or more sites, a question with no name

```cs
----------[ Bad ]----------

// in LabelFooters.cs
public static string Footer(Uri link)
{
    if (link.Scheme != Uri.UriSchemeHttp && link.Scheme != Uri.UriSchemeHttps)
    {
        return "";
    }

    return link.ToString();
}

// in Promises.cs
public static bool CanPromiseToday(Dispatchable order) => !order.OnHold && order.Paid && order.Lines > 0;

// in Dispatch.cs
public static string Status(Dispatchable order)
{
    if (order.Paid && !order.OnHold && order.Lines > 0)
    {
        return "pick";
    }

    return "wait";
}

// in TrackingLinks.cs
public static bool IsShowable(Uri link)
{
    return link.Scheme != Uri.UriSchemeHttp && link.Scheme != Uri.UriSchemeHttps ? false : link.Host.Length > 0;
}

----------[ Good ]----------

// in Dispatch.cs
public bool IsReady => Paid && !OnHold && Lines > 0;

// in Dispatch.cs
public static string Label(Dispatchable order) => order.IsReady ? "pick" : "wait";
```

The other 1 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `vendor/bin/commandments judge --skill=csharp/repeated-call-helper` — find every one of these in the codebase.
- `vendor/bin/commandments info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `csharp-repeated-guard`, `csharp-repeated-named-call`.
- `vendor/bin/commandments report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 2 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`backend/repeated-call-helper`](../../backend/repeated-call-helper/SKILL.md) — the same discipline in PHP.
- [`csharp/duplication`](../duplication/SKILL.md) — a whole method body written twice, rather than one call or condition.
- [`csharp/fix-at-the-source`](../fix-at-the-source/SKILL.md) — name the question where the data lives, not beside each caller.
