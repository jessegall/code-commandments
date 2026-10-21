---
name: commandments-typescript-flow
description: "Shaping a function body in a `.ts` file or a `<script setup>`: a check that decides whether the rest of it runs, an `if`/`else if` chain, a block nested three deep, an `else` after a branch that already returned or threw, or a loop that pushes to, splices out of or deletes from the very collection it walks. Read this BEFORE writing any of them, and when a TypeScript flow finding points here."
---

# TypeScript flow — turn away at the top, then go straight

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> A function's shape is the first thing anyone reads of it. A body that walks straight
> down — the cases that cannot go on turned away at the top, then the work — is read once;
> a body that nests its work inside the conditions it survived has to be held in the head
> a branch at a time. TypeScript makes this worse than most, because a narrowing `if` is
> how the compiler is told a value is there, so the arrow of conditions grows for a reason
> and stays for none.
> 

## The principle

### Turn away what cannot go on, at the top

A check that decides whether the rest of the function runs belongs at its top, with a
`return` or a `throw`. Narrowing counts: `if (!order) return;` tells the compiler the
value is there AND leaves the body flat, where `if (order) { … }` wrapping forty lines
tells it the same thing and buries the work.

```ts
// the body is the work, and the reader never leaves the left margin
function ship(order: Order | null, slot: Slot | null): Shipment {
    if (!order) throw new MissingOrder()
    if (!slot) throw new NoSlotOpen(order.id)

    return slot.take(order)
}
```

### Never move what you are walking

A walk counts on the positions it has not reached yet, and a `splice`, `shift`,
`unshift`, `sort` or `pop` under way moves every one of them: an element is stepped
over, or read a second time. Decide while you walk and change afterwards:

```ts
for (const line of lines) {
    if (line.isEmpty()) {
        removed.push(line)          // decide here
    }
}
lines = lines.filter(line => !removed.includes(line))   // change after
```

A copy is not the collection: `[...lines]`, `lines.slice()` and `Array.from(lines)` are
each a different array, and walking one while changing the other is sound and says so.

Growing is a different matter. A `push` or an `add` hands the walk something it then
reaches, which is how a work queue is written — walk the ids, add what each is bound to,
and the walk closes over the lot. Write it deliberately or not at all; nothing in the
code tells the two apart. Pruning a Set or a Map while walking it is defined too: the
walk sees the entries still there, and never the one removed.

### An `else` after a branch that left is not a branch

When the `if` returns or throws, its `else` is the rest of the function. Unwrap it. The
same goes for an `else if` ladder over one subject, which is a `switch`, a lookup object
or a method on the type that knows the answer.

### What is NOT this

A guard clause is not nesting, however many there are: three at the top of a function
read as a list of the things that would stop it. A `forEach` callback's `return` leaves
that one call and not the walk, so it is not a bail-out and never reads as one — which
is the one place where the flat-body rule and the loop rule say different things.

## Rules

- [ ] A walk counts on the positions it has not reached yet: never move or drop the elements of the array you are walking.
      _Decide while you walk and change afterwards — collect what to drop and `filter` it out once the loop is done — or walk a copy and say so: `[...items]`, `items.slice()` and `Array.from(items)` are each a different array, and changing the original while walking one of those is sound._

## Worked example

### typescript-collection-mutated-while-iterated

a `for…of` or `forEach` that splices, shifts, sorts or pops the very array it walks — every element after the change has moved, so the walk skips one or reads one twice

```ts
----------[ Bad ]----------

// Dropping the empty lines of a page. The walk reads the array as it stood when it began, so splicing out of it
// mid-walk moves every later line up one and the walk steps over the next of them. Below it, the FIX: decide while
// walking, change once afterwards.
export function prune(lines: string[]): void {
    for (const line of lines) {
        if (line.trim() === '') {
            lines.splice(lines.indexOf(line), 1)
        }
    }
}

----------[ Good ]----------

export function pruned(lines: string[]): string[] {
    const empty: string[] = []

    for (const line of lines) {
        if (line.trim() === '') {
            empty.push(line)
        }
    }

    return lines.filter((line) => !empty.includes(line))
}
```

## Commands

- `{{binary}} judge --skill=typescript/flow` — find every one of these in the codebase.
- `{{binary}} info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `typescript-collection-mutated-while-iterated`.
- `{{binary}} report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`frontend/vue-control-flow`](../../frontend/vue-control-flow/SKILL.md) — the same shape in a template: a branch that belongs in the script, not in the markup.
- [`typescript/absence`](../absence/SKILL.md) — WHETHER a value can be missing at all, which decides if the guard should exist.
