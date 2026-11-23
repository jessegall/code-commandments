---
name: commandments-shared-enums
description: "Writing a fixed set of string or number values (statuses, kinds, modes), a class of constants, a `switch`/`match` over an enum or over strings that mirror one, a membership list of literals, an or-chain over several cases, a side list of which cases have a trait, or a chain of type tests on one value. Read this BEFORE you write any of them, and when an enums finding points here."
---

# Enums — a closed set is a type that knows its cases

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> A closed set of values is a type, and what is known about each value lives on that type.
> Read this before you write a fixed set of strings or numbers, a class of constants, a `switch`/`match` over a
> status or a kind, a membership list of literals, or an `is this an A or a B?` type test, and when an enums finding
> points here.
> The examples are pseudocode: read them as the language you are writing.
> An arrow in an example (`←` or `↑`) marks the line the lesson is about.

## The principle

**Declare the cases once, as an enum, and keep everything keyed off a case on the enum.**

A status, a kind, a mode: a handful of values that will never be anything else. Written as raw strings, the set
exists only in the heads of the people who typed them: a typo is a silent new case, and nothing lists the values.
Written as an enum whose knowledge is scattered across call sites, every new case means hunting down each `switch`.

So two rules:

1. **The set is a type.** An enum, never loose literals and never a class of constants.
2. **Knowledge about a case lives with the case.** Its label, its colour, whether it counts as "open", what happens
   next: methods on the enum. A call site asks the enum; it does not re-derive the answer.

```pseudo
// Bad: the set is strings, and every call site knows what they mean
if (order.status == "paid" || order.status == "shipped") {   // ← strings whose meaning only people know
    badge = order.status == "paid" ? "green" : "blue"
}

// Good
enum OrderStatus {
    Pending, Paid, Shipped

    function isSettled(): bool { return this in [Paid, Shipped] }

    function colour(): string {
        return match (this) {
            Pending => "grey"
            Paid    => "green"
            Shipped => "blue"
        }
    }
}

if (order.status.isSettled()) {
    badge = order.status.colour()
}
```

## The sins

### const-class-enum

A class that only holds constants forming a closed set. It lists the cases but is not a type: any string still fits
where one is expected. Declare an enum.

```pseudo
// Bad
class Role {
    const ADMIN  = "admin"
    const EDITOR = "editor"
    const VIEWER = "viewer"
}

function grant(role: string) { … }   // ← any string still fits

// Good
enum Role { Admin, Editor, Viewer }

function grant(role: Role) { … }
```

### string-match-mirrors-enum

A `switch` over literal values that an enum already declares as its cases. The enum exists and the code dispatches
on its strings anyway. Dispatch on the type and its named cases.

```pseudo
// Bad
switch (payment.method) {
    case "card":   return chargeCard(payment)   // ← Method.Card exists; the string repeats it
    case "ideal":  return redirectToBank(payment)
    case "paypal": return redirectToPaypal(payment)
}

// Good
switch (payment.method) {
    case Method.Card:   return chargeCard(payment)
    case Method.Ideal:  return redirectToBank(payment)
    case Method.Paypal: return redirectToPaypal(payment)
}
```

### in-array-mirrors-enum

A membership check against a list of literals that repeats declared enum values. Use the named cases, or better, a
named group on the enum.

```pseudo
// Bad
if (status in ["paid", "shipped", "delivered"]) { … }   // ← repeats values the enum declares

// Good
if (status.isSettled()) { … }
```

### enum-case-or-chain

An or-chain that asks about several cases of one enum. That group of cases is a concept with no name, and the same
chain will be written again elsewhere, one case short. Give the group a named test on the enum.

```pseudo
// Bad
if (status == Status.Paid || status == Status.Shipped || status == Status.Delivered) { … }
// ↑ a group of cases that has no name

// Good
if (status.isSettled()) { … }

enum Status {
    function isSettled(): bool { return this in [Paid, Shipped, Delivered] }
}
```

### enum-value-match

A `match` or `switch` over an enum's backing value at a call site. This is per-case knowledge living away from the
cases. Put it on the enum as a method that answers for each case.

```pseudo
// Bad
label = match (order.status.value) {   // ← what each case means, written far from the cases
    "pending" => "Waiting for payment"
    "paid"    => "Paid"
    "shipped" => "On its way"
}

// Good
label = order.status.label()

enum Status {
    function label(): string {
        return match (this) {
            Pending => "Waiting for payment"
            Paid    => "Paid"
            Shipped => "On its way"
        }
    }
}
```

### side-table-trait

A separate list or map, somewhere else, that records which cases have a trait. Add a case and the side table is
silently out of date. Keep the trait beside the cases it describes.

```pseudo
// Bad
REFUNDABLE = [Status.Paid, Status.Shipped]   // ← a new case will not appear here by itself

if (order.status in REFUNDABLE) { … }

// Good
enum Status {
    function isRefundable(): bool { return this in [Paid, Shipped] }
}

if (order.status.isRefundable()) { … }
```

### type-switch

Branches that test the same value against several concrete types. It is really a `switch` over an enum: the
"cases" are the subtypes, and the behaviour belongs on them. Put the behaviour on the type that owns it.

```pseudo
// Bad
function price(freight): Money {
    if (freight is Parcel) { return parcelRate(freight.weight) }   // ← asks what it is, then does its work for it
    if (freight is Pallet) { return palletRate(freight.count) }
    if (freight is Letter) { return letterRate() }
}

// Good
interface Freight {
    function price(): Money
}

function price(freight: Freight): Money {
    return freight.price()
}
```

### compare-self — in PHP

A strict comparison (`===`) against a case, on an enum that has its own comparison method. Use the method wherever
the comparison is not needed for type narrowing, so every comparison of that enum reads the same way.

```php
// Bad
if ($order->status === OrderStatus::Paid) { … }   // ← the enum has is() for this

// Good
if ($order->status->is(OrderStatus::Paid)) { … }
```

## In your language

- **PHP.** A native backed `enum` with methods; never a `final class` of `const`. `match ($this)` inside the enum
  is exhaustive, so a new case fails loudly.
- **Python.** `class Status(Enum)` or `StrEnum`, with methods on it; a `Literal["a", "b"]` is still strings at
  runtime.
- **C#.** An `enum` plus extension methods, or a sealed class hierarchy when cases carry data; use a `switch`
  expression inside the method so a missing case warns.
- **TypeScript.** A union of string literals or an `as const` object typed as its keys; put the per-case answers in
  one `Record<Status, …>` beside it, never in scattered `switch`es.
- **Go.** A named type with `const` values via `iota`, and methods on that type.

## Commands

- `{{binary}} judge --skill=shared/enums` — find every one of these in the codebase.
- `{{binary}} info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `compare-self`, `const-class-enum`, `enum-case-or-chain`, `enum-value-match`, `in-array-mirrors-enum`, `side-table-trait`, `string-match-mirrors-enum`, `type-switch`.
- `{{binary}} report --detector=<Detector> --reason="…" --best-design="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`shared/tell-dont-ask`](../tell-dont-ask/SKILL.md) — the same move for any object: behaviour lives with its data.
- [`shared/flow`](../flow/SKILL.md) — how a dispatch that stays at the call site is shaped.
