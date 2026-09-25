### A copy is a decision made twice

Two functions with the same body are the same code whatever they are called — and the
case worth catching is exactly when they are NOT called the same, because then nobody
searching for one finds the other. Hoist the body to ONE home and let every caller use it:

- a module-level function in the package both callers already import, when it only computes;
- a method on the class that owns the data, when it reads one object's attributes;
- a method on a shared base class, when two subclasses each wrote the same override.

### A near-copy is a missing parameter

Two bodies with the same control flow that differ only in a literal — a path, a dict key,
an error message, the text of an f-string — are one function waiting for an argument.
Name what differs and pass it; do not keep two copies because the difference "is only a
string". The string is the parameter.

### What is NOT duplication

Short bodies are alike by coincidence: a one-line delegate, a property, a `return
self._x` cannot be hoisted into anything smaller than itself. Neither can two `__init__`s
of two different classes, two stubs (`pass`, `...`, `raise NotImplementedError`) that
leave the body to a subclass, or two lookup tables that call nothing and only return
constants — those are data, not procedure. Duplication is a body of real substance, twice.