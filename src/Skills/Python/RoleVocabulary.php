<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\Python;

use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class RoleVocabulary extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'python/role-vocabulary',
            tier: Tier::KeepInMind,
            order: 45,
        );
    }

    public function title(): string
    {
        return 'Python role vocabulary — a Registry, a Set, a Resolver, and the contract each name promises';
    }

    public function trigger(): string
    {
        return "Writing a Python class that holds a dict of things by key and hands them out, one that collects things to ask whether it holds one, or a chain of `if`s that picks the first handler that matches — or naming a class `*Registry`, `*Set` or `*Resolver`. Read this before you write `def get(self, key) -> X | None` on a store, and when a role-vocabulary finding points here.";
    }

    public function intro(): string
    {
        return "Three shapes recur everywhere: a keyed store, a membership set, a first-match dispatcher. Each has a name
and a contract. Name the class for the role and keep the contract — a `*Registry` whose `get` returns `None`,
or a `*Resolver` that does not dispatch, is a lie every caller pays for.";
    }

    public function summary(): string
    {
        return 'a keyed store / membership set / first-match dispatcher: name it `*Registry`/`*Set`/`*Resolver` and honour the contract — a registry `get` raises on a miss.';
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
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
PRINCIPLE;
    }

    public function related(): array
    {
        return [
            \JesseGall\CodeCommandments\Skills\Backend\RoleVocabulary::class => 'the same roles in PHP, with scaffolded bases.',
            Absence::class => 'a registry `get` raises on a miss — the same "a must-exist thing that is missing raises" rule.',
            Exceptions::class => 'the named exception a registry raises on a miss, built by a classmethod factory.',
        ];
    }

    public function languages(): array
    {
        return [Language::Python];
    }
}
