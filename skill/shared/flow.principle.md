### Guard at the top, then leave

Every precondition a function depends on is checked first and leaves: `return`, `throw`, `break`,
`continue`. By the time the work runs, everything it needs is there, so it sits at the function's own
level with no `else` and no nesting.

### No `else` after a branch that already left

When the `if` returns, throws, breaks or continues, what follows it only runs when the condition was
false. The `else` says nothing the exit did not already say, and it indents the rest for nothing. Drop
it and dedent.

### A loop body wrapped in one `if` is a `continue` guard

A loop whose whole body is one `if` is the same shape inside a loop. Flip the condition, `continue`,
and let the body sit at the loop's own level.

### A ladder over one subject is a dispatch

`if (kind == "paid") … else if (kind == "late") … else if …` tests one value case after case. Make
the closed set a type that answers per case, key a lookup by the value, or `switch`/`match` on it.

### One decision per conditional expression

A ternary (`a ? b : c`, `b if a else c`) chooses one value. A ternary inside another folds several
decisions into one line; unfold it into guards that return, a `match`/`switch`, or a lookup.

### Choose an action with an `if`

A ternary whose value nothing reads, or a bare `a && b()`, runs work off an operator. It is an `if`
in disguise: write the `if`.

### Write once what every branch ends with

A statement every branch of an `if`/`else` ends with runs whichever way the condition goes. It belongs
after the conditional, written once.

### Nothing after the block has left

A statement after a `return`, `throw`, `break` or `continue` in the same block can never run. Delete
it, or move it above the exit if it was meant to run.
