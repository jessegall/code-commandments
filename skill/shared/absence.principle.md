### Ask about absence directly

`(name ?? '') !== ''`, `(name or "") != ""`: the fallback turns "missing" into a blank, and the
comparison then asks whether the value is that same blank. Missing and empty end up in one branch
without anyone saying so. Ask the question you mean: whether the value is there (`name !== null`,
`name is not None`), and if empty counts too, say both halves out loud. Better still, make the value
always present where it is made, so only one question is left.

### A missing collection is a guard, not a loop header

`for item in items or []`, `foreach ($x[$k] ?? [] as …)`: the check for a collection that was never
handed over hides in the loop header. State it at the top with an early return, so the loop walks
something that is there, or make the caller always hand one over.
