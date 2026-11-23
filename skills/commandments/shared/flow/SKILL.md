---
name: commandments-shared-flow
description: "Shaping a function body in any language: an `if` that decides whether the rest runs, an `else` after a branch that already returned, a loop whose whole body sits under one `if`, an if/else-if chain over one value, a ternary inside a ternary, a ternary or `&&` used as a statement, a statement every branch repeats, code after a `return`, or a parameter given a new value. Read this BEFORE writing any of them, and when a flow finding points here."
---

# Flow — guard at the top, keep the body flat

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> A function body should read top to bottom, at one level: what cannot go on leaves first, and the work follows flat.
> Read this before you shape a function body in any language, and when a flow finding points here.
> The examples are pseudocode: read them as the language you are writing.
> An arrow in an example (`←` or `↑`) marks the line the lesson is about.

## The principle

**Guard at the top, leave early, keep the body flat.**

A function has two kinds of lines: the checks that decide whether the work happens at all, and the work. Put every
check first, and let each one leave (`return`, `throw`, `continue`, `break`) the moment it fails. What is left is the
work, at the function's own level, with nothing to remember from above.

Three habits follow from it, and every sin below breaks one of them:

1. **A condition that stops the work is a guard, not a wrapper.** Negate it, leave, and write the work after it. Never
   wrap the work in an `if`, and never write an `else` after a branch that already left.
2. **One decision, one construct.** A choice between values is a conditional expression. A choice between actions is an
   `if`. A choice among many cases of one subject is a dispatch (`switch`/`match`, a lookup, or a method each case
   answers). Do not fold several decisions into one expression, and do not spell a dispatch as a chain of `if`s.
3. **A statement is written where it runs, once.** Nothing after the block has left, nothing repeated at the end of
   every branch, and a name keeps the value it was given.

```pseudo
// Bad: the work sits two levels deep, behind conditions the reader has to keep in mind
function ship(order) {
    if (order.isPaid()) {   // ← the work waits behind this check
        if (order.hasStock()) {
            reserve(order)
            dispatch(order)
        } else {
            throw OutOfStock.for(order)
        }
    } else {
        throw Unpaid.for(order)
    }
}

// Good: each check leaves, and the work reads flat
function ship(order) {
    if (!order.isPaid()) {   // ← a guard: it fails, it leaves
        throw Unpaid.for(order)
    }
    if (!order.hasStock()) {
        throw OutOfStock.for(order)
    }

    reserve(order)
    dispatch(order)
}
```

## The sins

### redundant-else

An `else` after a branch that already left. The exit above already says the rest only runs when the condition did not
hold, so the `else` only indents it.

```pseudo
// Bad
if (order.isPaid()) {
    return order.receipt()
} else {   // ← the branch above already returned
    charge(order)
}

// Good
if (order.isPaid()) {
    return order.receipt()
}

charge(order)
```

### loop-inverted-guard

A loop whose whole body is one `if`. Every turn of the loop is pushed a level deep behind a condition. Negate the
condition, `continue`, and the body stays flat.

```pseudo
// Bad
for (line in invoice.lines) {
    if (line.isTaxable()) {   // ← the whole body of the loop sits inside this
        tax = tax + line.tax()
        taxed.add(line)
    }
}

// Good
for (line in invoice.lines) {
    if (!line.isTaxable()) {
        continue   // ← leave this turn early
    }

    tax = tax + line.tax()
    taxed.add(line)
}
```

### deep-nesting

A decision that sits deep inside other decisions. Turn the outer conditions into guards that leave, or move the inner
decision into a function with a name of its own.

```pseudo
// Bad
for (customer in customers) {
    if (customer.isActive()) {
        for (order in customer.orders) {
            if (order.isOverdue()) {
                if (order.total > limit) {   // ← five levels deep
                    escalate(order)
                }
            }
        }
    }
}

// Good: guards in the loop, and the inner decision named
for (customer in customers) {
    if (!customer.isActive()) {
        continue
    }

    escalateOverdue(customer.orders, limit)
}

function escalateOverdue(orders, limit) {
    for (order in orders) {
        if (!order.isOverdue() || order.total <= limit) {
            continue
        }

        escalate(order)
    }
}
```

### subject-ladder · if-else-ladder

A chain of four or more `if`/`else if` rungs that each compare the same subject with a constant. That is a dispatch
written as a ladder. Put the per-case answer on the type, key a lookup by the value, or use a `switch`/`match`.

```pseudo
// Bad
if (status == "draft") {
    label = "Concept"
} else if (status == "sent") {   // ← the same subject, compared again
    label = "Sent"
} else if (status == "paid") {
    label = "Paid"
} else if (status == "void") {
    label = "Cancelled"
}

// Good: the closed set is an enum that answers for each case
enum Status {
    Draft, Sent, Paid, Void

    function label() {
        return match (this) {
            Draft => "Concept"
            Sent  => "Sent"
            Paid  => "Paid"
            Void  => "Cancelled"
        }
    }
}

label = status.label()
```

### nested-ternary

A conditional expression with another inside it: several decisions folded into one expression. One conditional
expression makes one choice. For more, use a `match`/`switch`, a lookup, or a small function whose guards return early.

```pseudo
// Bad
rate = customer.isBusiness() ? (order.isExport() ? 0 : 21) : (order.isBook() ? 9 : 21)
// ↑ three decisions in one expression

// Good
function rateFor(customer, order) {
    if (customer.isBusiness() && order.isExport()) {
        return 0
    }
    if (!customer.isBusiness() && order.isBook()) {
        return 9
    }

    return 21
}
```

### ternary-statement

A conditional expression used as a statement. Nothing reads its value, so it is choosing an action, and an action is
chosen with an `if`.

```pseudo
// Bad
order.isPaid() ? ship(order) : remind(order)   // ← nothing reads the value: this picks an action

// Good
if (order.isPaid()) {
    ship(order)
} else {
    remind(order)
}
```

### short-circuit-statement

A bare `a && b()` or `a || b()` whose value nothing reads. The operator is doing the work of an `if`.

```pseudo
// Bad
order.isPaid() && ship(order)   // ← an if, written as an operator
cache.has(key) || cache.fill(key)

// Good
if (order.isPaid()) {
    ship(order)
}
if (!cache.has(key)) {
    cache.fill(key)
}
```

### unnamed-condition

A condition of three or more parts over two or more objects. The reader has to work out what it decides. Give the
decision a name, on the type that owns it.

```pseudo
// Bad
if (user.isActive() && !user.isSuspended() && order.total < account.limit && account.isOpen()) {
// ↑ four checks over three objects: what is being decided?
    approve(order)
}

// Good
if (account.canPlace(order, user)) {   // ← the decision has a name
    approve(order)
}
```

### duplicated-branch-tail

Every branch ends with the same statement, so it runs whichever way the condition goes. Write it once, after the
conditional.

```pseudo
// Bad
if (order.isExpress()) {
    courier.book(order)
    notify(order.customer)   // ← the last line of this branch
} else {
    post.book(order)
    notify(order.customer)   // ← and of this one
}

// Good
if (order.isExpress()) {
    courier.book(order)
} else {
    post.book(order)
}

notify(order.customer)   // ← once, after the choice
```

### unreachable-statement

A statement after one that never completes: a `return`, a `throw`, a `break` or `continue`, an `if` whose every arm
leaves. It can never run. Delete it, or move it above the exit if it was meant to run.

```pseudo
// Bad
function close(account) {
    if (account.hasBalance()) {
        throw BalanceLeft.for(account)
        log("refused to close", account)   // ← never runs: the throw above already left
    }

    return account.closed()
    audit(account)   // ← never runs: it stands after the return
}

// Good
function close(account) {
    if (account.hasBalance()) {
        log("refused to close", account)
        throw BalanceLeft.for(account)
    }

    audit(account)

    return account.closed()
}
```

### reassigned-parameter

A parameter overwritten inside its function. One name then holds the value the caller passed and, later, a different
one. A new value gets a name of its own.

```pseudo
// Bad
function register(name) {
    name = trim(name)   // ← from here on, name is no longer what the caller passed
    name = lowercase(name)

    return Account.named(name)
}

// Good
function register(name) {
    normalised = lowercase(trim(name))

    return Account.named(normalised)
}
```

### non-counting-for

A `for` loop whose step assigns the next item instead of moving a counter: a walk written as a count. Keep `for` for
counting. Walk with a `while`, or better, let the type hand out its own sequence so the caller never holds the cursor.

```pseudo
// Bad
for (node = list.head; node != null; node = node.next) {
// ↑ the step moves to the next item; nothing is counted
    visit(node)
}

// Good
for (node in list.nodes()) {
    visit(node)
}
```

### collection-mutated-while-iterated

A loop that adds to or removes from the very collection it walks. The iterator reads a collection that moved under
it: an error in some languages, skipped items in others. Decide inside the loop and change afterwards.

```pseudo
// Bad
for (session in sessions) {
    if (session.isExpired()) {
        sessions.remove(session)   // ← changes the collection the loop is walking
    }
}

// Good
expired = sessions.filter(session => session.isExpired())

for (session in expired) {
    sessions.remove(session)
}
```

### inline-throw — in PHP

A `?? throw` fed straight into a call or dereferenced on the same line. The check that stops the method is hidden in
the middle of the work. Guard at the top, then work with the checked value.

```php
// Bad
$total = $this->pricing->totalFor($request->order ?? throw MissingOrder::in($request));
// ↑ the check that stops the method hides inside the call

// Good
$order = $request->order ?? throw MissingOrder::in($request);

$total = $this->pricing->totalFor($order);
```

### csharp-inline-throw — in C#

The same in C#: a `?? throw` inside a call's argument or in front of a member call.

```csharp
// Bad
var total = pricing.TotalFor(request.Order ?? throw MissingOrder.In(request));
// ↑ the check that stops the method hides inside the call

// Good
var order = request.Order ?? throw MissingOrder.In(request);

var total = pricing.TotalFor(order);
```

## In your language

- **Python.** There is no `switch` before `match`; a ladder over one subject becomes a `match`, a dict keyed by the
  value, or a method on an `Enum`. Indentation is the nesting, so every guard that leaves saves a visible level.
- **Go.** The error check is the guard: `if err != nil { return … }` first, then the work at the function's own
  level. Never write the happy path inside `if err == nil { … }`.
- **PHP.** A dispatch on a value is a `match`, or a method on a backed enum.
- **C#.** A dispatch on a value is a `switch` expression; a guard that throws can be `ArgumentNullException.ThrowIfNull`.
- **TypeScript.** A dispatch over a union is a `switch` on its discriminant, or a `Record` keyed by it.

## Commands

- `{{binary}} judge --skill=shared/flow` — find every one of these in the codebase.
- `{{binary}} info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `collection-mutated-while-iterated`, `csharp-inline-throw`, `deep-nesting`, `duplicated-branch-tail`, `if-else-ladder`, `inline-throw`, `loop-inverted-guard`, `nested-ternary`, `non-counting-for`, `reassigned-parameter`, `redundant-else`, `short-circuit-statement`, `subject-ladder`, `ternary-statement`, `unnamed-condition`, `unreachable-statement`.
- `{{binary}} report --detector=<Detector> --reason="…" --best-design="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.
