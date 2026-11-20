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
two different classes, or two scripts that hand their own literals to one callee one
statement after another, as two migrations hand `addSql` their SQL — with the literals
a parameter, all that is left to share is the callee. Duplication is a body of real
substance, twice.

## Related skills

- [`php/fix-at-the-source`](../../php/fix-at-the-source/SKILL.md) — the same instinct on the server — one decision, made once, where it is born.
- [`vue/vue-components`](../../vue/vue-components/SKILL.md) — the template twin: markup written twice is a component waiting to be extracted.
