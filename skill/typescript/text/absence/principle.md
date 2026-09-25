### The type is the claim; the defence is the tell

`field?: T` and `T | null` are claims that the value can be missing. If every path
writes it before anything reads it, the claim is false — and the `?.` and `??` scattered
downstream exist only to satisfy a compiler about a case the program never has. Delete
the optionality and the defences go with it.

The reverse is the same rule read backwards: where a value REALLY can be missing, the
absence is a case to handle, not a hole to fill. `?? 0`, `?? ''`, `?? []` answer the
compiler and lose the question — was it missing, or was it genuinely zero?

### `null` and `undefined` are not two names for one thing

Pick one to MEAN "missing" and let the other be a bug. A property that is sometimes
`null` and sometimes `undefined` forces every reader to test for both, and a test for
one is a silent hole for the other. The two are distinguishable at the type level, so
a codebase that uses both interchangeably has thrown that away for nothing.

### What TypeScript does NOT get from the backend

There is no `Option` here, and no Null Object worth the ceremony for a plain data
shape. The tools are the type itself (`T` vs `T | null`), a narrowing guard at the top
of the function, and a total value the caller can always use. That is the whole kit —
which is why this is its own skill rather than a translation of the PHP one.