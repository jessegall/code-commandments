### Ask in order, stop at the first yes

1. **Can it actually be missing, or is "missing" a broken state?** A config the program cannot
   run without, a record a caller just created, a key the code itself wrote — absence there is a
   failure. **Raise a named exception.** Do not return `None` for it.
2. **Does "nothing" have a natural empty form?** A search with no hits is `[]`; a mapping with no
   entries is `{}`; a behaviour with nothing to do is a Null Object — an instance whose methods do
   nothing. Return that, and every caller loops or calls with no special case.
3. **Is it a genuine "look for it; it may miss" that more than one caller handles?** Then an
   annotated `X | None` is honest — it is Python's `Option`: a type checker makes every caller
   handle the missing case, and the check belongs at each caller *because* each one decides
   something different. If every caller writes the same `if result is None: raise …` or `or
   default`, the decision was the producer's: move it there.

### `or default` answers the wrong question

`name = user.name or ""` fills a required slot with a value nobody chose, and loses the question:
was the name missing, or genuinely empty? `x or []` also swallows `0`, `False` and `""` — every
falsy value, not just `None`. If the value can be missing, handle that case; if it cannot, do not
defend against it.

### The one honest `None`

A single local lookup checked right where it is produced — one caller, one `is None`, done — needs
no ceremony. The smell is a `None` that **travels**: returned, passed on, and re-checked at every
place it lands.