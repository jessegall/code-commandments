The relationship runs **both ways**:

- **Shape → name.** A class hand-rolling one of these shapes — a dict plus `register` plus a lookup, an
  add-and-ask collection, an `if` chain returning the first handler that matches — is named for the role.
- **Name → shape.** A class *named* `*Registry`, `*Set` or `*Resolver` behaves like one. The suffix is a
  promise about the contract; breaking it misleads every reader.

And one rule across all three: **a role class does one job.** A registry that also resolves, queries or
assembles is hosting a second engine — move it out.

### Registry — a keyed store

```python
class HandlerRegistry:
    def __init__(self) -> None:
        self._handlers: dict[str, Handler] = {}

    def register(self, kind: str, handler: Handler) -> None:
        self._handlers[kind] = handler

    def get(self, kind: str) -> Handler:
        try:
            return self._handlers[kind]
        except KeyError as missing:
            raise UnknownHandler.for_kind(kind) from missing

    def __contains__(self, kind: str) -> bool:
        return kind in self._handlers
```

- **`get` returns the item or raises** — never `-> Handler | None`. A miss on a registry is a broken
  invariant (nothing registered what the code relies on), not a value for every caller to branch on. Ask
  `kind in registry` first where a miss is genuinely expected.
- **It stays a store** — no resolving, querying or building inside it.

### Set — membership, unkeyed

`add(item)`, `__contains__(item) -> bool`, `__iter__`. Nothing is looked up by key: if you want an item
*by key*, you wanted a Registry.

### Resolver — first-match dispatch

A sequence of `(predicate, handler)` pairs walked in order, the first predicate that matches deciding the
answer — not an `if`/`elif` ladder re-testing one value, and not a dict lookup dressed up as one (that is
a Registry). A class named `*Resolver` that does not dispatch is renamed.

### Classify by type, not a name list

When a role decides "is this one of mine?", it asks the type — a base class, a `Protocol`, an
`isinstance` against something the code declares — never a hardcoded list of class names that rots as
classes are added and renamed.