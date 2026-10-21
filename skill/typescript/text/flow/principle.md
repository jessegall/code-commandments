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
