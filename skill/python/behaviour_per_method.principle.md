A parameter is something a function **works with**. A flag argument is something it works *around*: it
arrives, is tested once, and decides which half of the body runs. The two halves were never one function —
they share a name and a signature, and nothing else.

- **`send(order, True)` is unreadable.** Nothing at the call says what `True` means.
- **The halves cannot evolve apart.** A parameter one half needs becomes one the other must ignore.
- **It hides how the code is used.** Split, `send_draft` has three callers and `send_final` thirty.
- **Flags multiply.** Two bools are four behaviours in one body, and usually three were meant.

So **name the two behaviours**: `render(order, True)` becomes `render_compact(order)` and
`render_full(order)`, and whatever they share becomes a private function both call.

### A keyword does not split it

`render(order, *, compact: bool = False)` makes the call say `compact=True`, and that is better than a bare
`True` — but the body still runs one of two jobs behind one name. Keyword-only is how to pass a flag that
TUNES a behaviour; a flag that SELECTS one is still two functions.

### The disguise: `None` that means "all of them"

A required parameter widened so that leaving it out asks a different question — `fields(kind: str)` becomes
`fields(kind: str | None = None)`, and `fields()` now means "every one". That is two questions behind one
name, selected by an ABSENCE at the call, which says even less than a bare `True`. The fix is the same:
`fields_of_kind(kind)` and `fields()`.

### What is NOT this sin

- **A flag that is DATA the function stores or forwards** — `set_visible(visible)`: the value is the point.
- **A flag that tunes ONE behaviour** — `parse(text, strict=True)`, where strictness changes what counts as
  an error inside one pass rather than choosing another pass.
- **A guard at the top** — `if force: return self._overwrite()` is a guard clause (see `python/flow`). The
  smell is a body that is *nothing but* the two-way branch.
- **An enum that names the choice** — `render(order, Density.COMPACT)` already says what it means.

### The tell

The whole body is `if flag: … else: …`, a `match flag:` with a `True` and a `False` case, or
`if kind is None: … else: …` on a parameter that used to be required. Ask what you would call each half on
its own. If both have an obvious name, they are already two functions — give them their names.