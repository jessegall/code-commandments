---
name: commandments-typescript-duplication
description: "Copying a function body from one component or module into another — the same `load`/`format`/`submit` written a second time in a different `<script setup>` or `.ts` file, or a near-copy that differs only in the endpoint, the field or the label it uses. Read this BEFORE pasting a function you already wrote somewhere else, and when a `duplicate-function` finding points here. The fix is a shared function or composable that both call, parameterised by whatever actually differs."
---

# TypeScript duplication — one behaviour, one home

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> A function written twice is one decision living in two places. The day it has to
> change, one copy gets the fix and the other keeps the bug — and nothing in either file
> says the other exists. In a Vue codebase this happens one component at a time: each
> `<script setup>` grows its own `formatPrice`, its own `loadPage`, until the behaviour
> the app depends on is spread across files that do not know about each other.

## The principle

### A copy is a decision made twice

Two functions with the same body are the same code whatever they are called — and the
case worth catching is exactly when they are NOT called the same, because then nobody
searching for one finds the other. Hoist the body to ONE home and let every caller use it:

- a plain function in a shared module (`utils/money.ts`) when it only computes;
- a composable (`useOrders()`) when it holds reactive state or lifecycle;
- a method on the class that owns the data, when it reads one object's fields.

### A near-copy is a missing parameter

Two bodies with the same control flow that differ only in a literal — an endpoint, a
field name, a label — are one function waiting for an argument. Name what differs and
pass it; do not keep two copies because the difference "is only a string". The string
is the parameter.

### What is NOT duplication

Short bodies are alike by coincidence: a one-line delegate, a getter, a `return x.y`
cannot be hoisted into anything smaller than itself. Neither can two constructors of
two different classes. Duplication is a body of real substance, twice.

## Rules

- [ ] Hoist a function body written twice into one shared function or composable, and call it from both places.
      _Move the body to a shared module or composable under one name, and replace every copy with a call to it._
- [ ] Merge two functions that differ only in a literal into one, and pass what differs as a parameter.
      _Name the literal that differs, make it a parameter of one shared function, and call that from both places._

## Worked example

### duplicate-typescript-function — in TypeScript

Copy-pasted code — two+ TypeScript functions (a `function`, a method, a `const` arrow; in a `.ts` module or a component's script) with an identical body, formatting and comments aside

```ts
----------[ Bad ]----------

// in packing-slip.ts
rows(lines: Line[]): string[] {
    const rows: string[] = []
    for (const line of lines) {
        if (line.quantity <= 0) {
            continue
        }
        rows.push(`${line.quantity} × ${line.name}: ${(line.quantity * line.unitPrice).toFixed(2)}`)
    }
    return rows
}

// in packing-slip.ts
lineItems(lines: Line[]): string[] {
    const rows: string[] = []
    for (const line of lines) {
        if (line.quantity <= 0) {
            continue
        }
        rows.push(`${line.quantity} × ${line.name}: ${(line.quantity * line.unitPrice).toFixed(2)}`)
    }
    return rows
}

// in stock-lookup.ts
export async function loadStockLevels(sku: string): Promise<number[]> {
    const response = await fetch(`/api/stock/${sku}`)
    if (!response.ok) {
        throw new Error(response.statusText)
    }
    const levels: StockLevel[] = await response.json()
    return levels.map((level) => level.available)
}

----------[ Good ]----------

// in order-lines.ts
export function describeLines(lines: Line[]): string[] {
    return lines
        .filter((line) => line.quantity > 0)
        .map((line) => `${line.quantity} × ${line.name}: ${(line.quantity * line.unitPrice).toFixed(2)}`)
}

// in order-lines.ts
rows(lines: Line[]): string[] {
    return describeLines(lines)
}

// in order-lines.ts
lineItems(lines: Line[]): string[] {
    return describeLines(lines)
}
```

The other 3 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `vendor/bin/commandments judge --skill=typescript/duplication` — find every one of these in the codebase.
- `vendor/bin/commandments info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `duplicate-typescript-function`, `near-duplicate-typescript-function`.
- `vendor/bin/commandments report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 4 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`backend/fix-at-the-source`](../../backend/fix-at-the-source/SKILL.md) — the same instinct on the server — one decision, made once, where it is born.
- [`frontend/vue-components`](../../frontend/vue-components/SKILL.md) — the template twin: markup written twice is a component waiting to be extracted.
