### Trace it upstream before you change a line

A finding is a symptom. Before adding an `if x is None`, a default or a `try` where it surfaced, ask
where the value came from and walk back until you reach the place that made it. Fix that place, and
the check you were about to write — and every copy of it — is no longer needed.

### An `__init__` builds; it does not act

A constructor says what the object IS: it stores what it was given and derives what it needs. When it
tells a collaborator to DO something — warm a cache, register itself, open a connection — and throws
the answer away, merely creating the object changes the world, in a line of code that reads like
bookkeeping. Keep the collaborator as an attribute and act on it from the method someone calls, at the
moment they chose.

### State that changes lives on an instance

A module-level variable written by a `global`, or a class attribute assigned from a method, is state
every caller shares and nobody passes. Who changed it, and when, is written nowhere. Hold changing
state on an instance, pass that instance to the code that needs it, and the dependency is in the
signature where a reader can see it.