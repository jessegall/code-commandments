### Ask in order, stop at the first yes

1. **Can it actually be missing, or is "missing" a broken state?** A setting the program cannot run
   without, a record a caller just created, a key the code itself wrote — absence there is a
   failure. **Throw a named exception.** Do not return `null` for it.
2. **Does "nothing" have a natural empty form?** A search with no hits is an empty list; a lookup
   table with no entries is an empty dictionary; a behaviour with nothing to do is a Null Object —
   an instance whose members do nothing. Return that, and every caller loops or calls with no
   special case.
3. **Is it a genuine "look for it; it may miss" that more than one caller handles?** Then `T?`
   under nullable reference types is honest — the compiler makes every caller handle the missing
   case, and the check belongs at each caller *because* each one decides something different.
   `TryGetValue(key, out var value)` says the same for a lookup. If every caller writes the same
   `?? throw …` or `?? default`, the decision was the producer's: move it there.

### `?? default` answers the wrong question

`var name = user.Name ?? "";` fills a required slot with a value nobody chose, and loses the
question: was the name missing, or genuinely empty? If the value can be missing, handle that case;
if it cannot, the type should say so — make it non-nullable where it is born.

### `!` silences the compiler instead of deciding

The null-forgiving operator — `order.Customer!.Name` — tells the compiler "trust me" about a value
it has just told you may be null. Nothing checks the promise; the `NullReferenceException` simply
moves to wherever it turns out to be wrong. Either the value cannot be null — then give it a type
that says so — or it can, and the missing case needs handling, not hushing.

### The one honest `null`

A single local lookup checked right where it is produced — one caller, one `is null`, done — needs
no ceremony. The smell is a `null` that **travels**: returned, passed on, and re-checked at every
place it lands.