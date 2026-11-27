---
name: commandments-shared-duplication
description: "Pasting a function body you already wrote, writing a near-copy that differs only in a name or a value, writing out the same compound condition, type check or builder call at a second site, converting a value the same way before every call, spelling out a folder path again, typing a literal that has a named constant, or listing by hand the classes a folder already holds. Read this BEFORE you write the second copy, and when a duplication finding points here."
---

# Duplication — one piece of knowledge, one home

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> One piece of knowledge has one home. A second copy is not a convenience: it is the place the next change will be
> forgotten.
> Read this before you paste a function, write out a condition or a call you have written before, list by hand what
> the code already knows, or type a path or a literal for the third time, and when a duplication finding points here.
> The examples are pseudocode: read them as the language you are writing.
> An arrow in an example (`←` or `↑`) marks the line the lesson is about.

## The principle

**Write it once, give it a name, and put it with the thing it is about.**

Duplication is rarely two identical files. It is the same compound condition at four call sites, the same
conversion before every call, the same folder spelled out in six strings, a list that repeats what a directory
already says. Each copy is small, and each is a place where a fix lands in one site and not the others.

Before writing something, look for it. When you find it, do not copy it: move it to one owner and call it. The
owner is almost always the object the code is about: a condition about an order becomes a method on the order.

What differs between two near-copies is not a reason to keep both. It is the parameter of the one function they
should share.

```pseudo
// Bad: the rule for "can be refunded" lives at every site that needs it
if (order.isPaid() && !order.isRefunded() && order.age() < 30) { showRefundButton() }
…
if (order.isPaid() && !order.isRefunded() && order.age() < 30) { refund(order) }

// Good: it lives on the order, once, with a name
if (order.isRefundable()) { showRefundButton() }
…
if (order.isRefundable()) { refund(order) }
```

## The sins

### duplicate-function

Two functions with the same body. Move the body to one owner and have each caller use it.

```pseudo
// Bad
class InvoiceMailer {
    function footer() { return company.name + " · " + company.vatNumber + " · " + company.address }
}

class QuoteMailer {
    function footer() { return company.name + " · " + company.vatNumber + " · " + company.address }
    // ↑ the same body as InvoiceMailer.footer()
}

// Good
class Company {
    function letterFooter() { return this.name + " · " + this.vatNumber + " · " + this.address }
}
```

### near-duplicate-function

Two functions that run the same code with different names or data. The difference is a parameter that has not been
named yet.

```pseudo
// Bad
function exportOrders() {
    rows = orders.all().map(order => order.toRow())
    file.write("exports/orders.csv", csv(rows))
}

function exportInvoices() {
    rows = invoices.all().map(invoice => invoice.toRow())
    file.write("exports/invoices.csv", csv(rows))   // ← the same steps; only the name and the records differ
}

// Good
function export(name: string, records: Exportable[]) {
    rows = records.map(record => record.toRow())
    file.write("exports/" + name + ".csv", csv(rows))
}
```

### divergent-twin

Two functions that share most of their steps, where one misses a step the other takes. They were copies once, and
a fix reached only one of them. This is the bug that duplication causes, and it has already happened. Extract the shared work, pass the real
differences as inputs, and decide on purpose whether the missing step belongs in both.

```pseudo
// Bad
function cancelByCustomer(order) {
    order.markCancelled()
    stock.release(order)
    refunds.issue(order)
    mail.sendCancellation(order)
}

function cancelByAdmin(order) {
    order.markCancelled()
    refunds.issue(order)                 // ← stock is never released
    mail.sendCancellation(order)
}

// Good
function cancel(order, by: Actor) {
    order.markCancelledBy(by)
    stock.release(order)
    refunds.issue(order)
    mail.sendCancellation(order)
}
```

### repeated-guard

A compound condition about an object, written out at more than one site. Put a named predicate on the object that
owns most of the condition.

```pseudo
// Bad
if (user.isActive && user.emailVerifiedAt != null && !user.isBanned) { … }
…
if (user.isActive && user.emailVerifiedAt != null && !user.isBanned) { … }
// ↑ the same three checks, written again

// Good
if (user.canSignIn()) { … }
```

### repeated-type-guard

The same compound type check written out at more than one site. The combination is a concept: give it a predicate
whose name says which values it accepts.

```pseudo
// Bad
if (node is Element && node.parent is Form) { … }
…
if (node is Element && node.parent is Form) { … }   // ← the same check, a second time

// Good
if (isFormField(node)) { … }

function isFormField(node): bool {
    return node is Element && node.parent is Form
}
```

### repeated-named-call

The same builder-style call with the same named argument at several sites. Every site spells out how, where one
method on the type could say what.

```pseudo
// Bad
event = event.with(metadata: payload.metadata())
…
job = job.with(metadata: payload.metadata())   // ← the same call, spelled out again

// Good
event = event.withMetadataOf(payload)
…
job = job.withMetadataOf(payload)
```

### repeated-static-factory

One function asks a static factory for the same object twice, with the same arguments, and asks each result one
question. Call the factory once, hold the object, and ask it both.

```pseudo
// Bad
name = Reflector.of(type).shortName()
parent = Reflector.of(type).parent()   // ← asks the factory for the same object again

// Good
reflected = Reflector.of(type)

name = reflected.shortName()
parent = reflected.parent()
```

### repeated-reach

The same member of another object is reached through at several sites: `request.headers.get(…)` written out once
per value. Each copy hides what it reads behind how it reads it. Reach through once, in a method that names the
value. When the object is yours, give it that method (`grant.isHolding()`, not `grant.status.isHolding()`); when it
is not, wrap it in a reader, one small type that holds the object and has one method per value.

```pseudo
// Bad
function of(request) {
    call = LoaderInput.fromHeader(request.headers.get(LoaderInput.HEADER))
    loader = declarations.loader(request.headers.get(LoaderCall.HEADER))   // ← reaches into the headers again
    …
}

// Good
class LoaderCall {
    constructor(private headers)

    static of(request) { return new LoaderCall(request.headers) }
    loader() { return headers.get(LoaderCall.HEADER) }
    input() { return LoaderInput.fromHeader(headers.get(LoaderInput.HEADER)) }
}

function of(request) {
    call = LoaderCall.of(request)
    loader = declarations.loader(call.loader())   // ← each value read by its name
    …
}
```

### converted-argument

Most callers convert a value the same way just to satisfy one parameter. The conversion belongs inside the
function: take the original typed value and convert it where it is used.

```pseudo
// Bad
schedule(date.toIsoString())
schedule(deadline.toIsoString())
schedule(reminder.at.toIsoString())   // ← every caller converts in the same way

function schedule(at: string) { … }

// Good
schedule(date)
schedule(deadline)
schedule(reminder.at)

function schedule(at: Date) { … }
```

### repeated-folder-path

One folder spelled out at three or more sites. Rename the folder and you are searching strings. Put it in one
constant or method and build paths from that.

```pseudo
// Bad
file.read("storage/exports/" + name)
file.delete("storage/exports/" + old)
file.list("storage/exports")   // ← the third copy of this folder

// Good
class Exports {
    const FOLDER = "storage/exports"

    static function path(name) { return FOLDER + "/" + name }
}

file.read(Exports.path(name))
```

### unnamed-vocabulary-literal

A string literal passed where a named constant for that exact value already exists. The name was the point: it is
what makes a typo a compile error and a rename one change.

```pseudo
// Bad
queue.push(job, "high-priority")   // ← Queue.HIGH_PRIORITY already holds this value

// Good
queue.push(job, Queue.HIGH_PRIORITY)
```

### list-mirrors-folder

A hand-written list that names every concrete class of one type in its folder. The folder already is that list;
the copy goes stale the day a class is added. Discover the implementations from their shared type, or let each one
register itself.

```pseudo
// Bad
HANDLERS = [CardHandler, IdealHandler, PaypalHandler, BankTransferHandler]
// ↑ a new handler must be remembered here

// Good
HANDLERS = implementationsOf(PaymentHandler)
```

## In your language

- **PHP.** A repeated condition becomes a method on the model or value object; a shared body moves to the class
  that owns the data, not to a `Helper`.
- **Python.** One module-level function both callers import; a repeated `isinstance(x, A) and isinstance(x.y, B)` is
  a `TypeGuard` function.
- **C#.** A method on the type, or an extension method when the type is not yours.
- **TypeScript, Vue.** A shared function or composable both components import; a repeated type check is a type
  predicate (`node is FormField`).
- **Go.** One function in the package that owns the type; near-copies that differ by type are one generic function.

## Commands

- `{{binary}} judge --skill=shared/duplication` — find every one of these in the codebase.
- `{{binary}} info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `converted-argument`, `divergent-twin`, `duplicate-function`, `list-mirrors-folder`, `near-duplicate-function`, `repeated-folder-path`, `repeated-guard`, `repeated-named-call`, `repeated-reach`, `repeated-static-factory`, `repeated-type-guard`, `unnamed-vocabulary-literal`.
- `{{binary}} report --detector=<Detector> --reason="…" --best-design="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`shared/tell-dont-ask`](../tell-dont-ask/SKILL.md) — where the one copy usually belongs: on the object it is about.
- [`shared/enums`](../enums/SKILL.md) — a repeated group of cases gets a named test on the enum.
