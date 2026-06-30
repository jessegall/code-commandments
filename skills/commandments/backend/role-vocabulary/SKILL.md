---
name: role-vocabulary
description: The three recurring structural roles — Registry (keyed store), Set (membership), Resolver (first-match dispatch) — each with a name and a contract. If a class IS one of these shapes, name it `*Registry`/`*Set`/`*Resolver` and extend the scaffolded base; if it's NAMED one, it must behave like one. Read this BEFORE you hand-roll a keyed store / lookup table, an add-and-iterate collection, or an if/elseif chain that picks the first matching handler — or name a class `*Registry`/`*Set`/`*Resolver`.
---

# Role vocabulary — the name is the contract

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> Three shapes recur everywhere: a keyed store, a membership set, a first-match dispatcher. Each has a
> name and a contract. Use the name, extend the base, and honour the contract — a `*Registry` that returns
> `null`, or a `*Resolver` that doesn't dispatch, is a lie.

## The principle

The relationship runs **both ways**:

- **Shape → name.** A class hand-rolling one of these shapes (an array + `register` + lookup; an
  add-and-iterate collection; a first-match `if/elseif` chain) should be **named for the role and extend
  the scaffolded base**, not reinvent the plumbing.
- **Name → shape.** A class *named* `*Registry`/`*Set`/`*Resolver` **must behave like one.** The suffix is
  a promise about the contract; breaking it misleads every reader.

And one cross-cutting rule: **a role class does ONE job.** A `*Registry` that also resolves, queries, or
assembles is hosting a second engine — extract it.

## Registry — a keyed store

`register(key, item)`, `get(key)`, `has(key): bool`, `all()`. Extends the scaffolded `Registry` base; named
`*Registry`.

- **`get($key)` returns the item or THROWS** — never `?T` / `Option` for the primary getter. A miss is a
  broken-state invariant, not a value to branch on (a `find()`/`try*` finder may return `Option`). This is
  the [`absence`](../absence/SKILL.md) resolve-or-throw rule, on the store.
- **Stays a pure store** — no resolution/query/assembly logic living inside it.

```php
// Bad — a hand-rolled keyed store that returns null on a miss
class Handlers {
    private array $map = [];
    public function add(string $k, Handler $h): void { $this->map[$k] = $h; }
    public function get(string $k): ?Handler { return $this->map[$k] ?? null; }   // null on miss
}

// Good — named for the role, extends the base, throws on a miss
final class HandlerRegistry extends Registry {
    // register() / has() / all() inherited;
    // get($key) returns the item or throws RegistryEntryNotFoundException::forKey($key)
}
```

## Set — membership + iteration, unkeyed

`add(item)`, `has(item): bool` (identity), `all()`. Extends the scaffolded `Set` base; named `*Set`.

- Total + iterate-only: `has()` returns `bool`, never an Option/nullable leak.
- **No keyed `get(string)`.** If you want to look an item up *by key*, you wanted a **Registry**, not a Set.

## Resolver — first-match dispatch over predicates

A chain that runs predicates and returns the first match's result. Built with `Resolver::firstResultWins(...)`
(or `::collect(...)`), predicates composed from the kernel (`is`, `anyOf`, `allOf`, negation), branches via
`->then($factory)`. Named `*Resolver`; **must actually do first-match dispatch** (else rename).

```php
// Bad — a ||/&& chain of predicate calls, hand-rolled dispatch
if ($p->matches($x) || $q->matches($x) || $r->matches($x)) { ... }

// Good — compose predicates; let the resolver dispatch
Resolver::firstResultWins(
    Predicate::is(Foo::class)->then(FooFactory::make(...)),
    anyOf($p, $q, $r)->then(...),
);
```

Compose classifier checks with `anyOf()`/`allOf()`, not a `||`/`&&` chain of `->matches()`.

## Classify by type, not a name list

When a role needs to decide "is this one of mine?", classify from a **marker interface or the AST/type**,
never a hardcoded `const` array of class-name strings. A name list silently rots as classes are renamed or
added; a marker interface is checked by the compiler.

```php
// Bad — a classifier driven by a string list
private const HANDLERS = ['FooHandler', 'BarHandler'];

// Good — a marker interface the type system enforces
$handler instanceof Handler
```

## Checklist

```
Role vocabulary
- [ ] A hand-rolled keyed store / set / first-match chain is named *Registry/*Set/*Resolver and extends the base.
- [ ] A *Registry's get() returns the item or THROWS — no Option/nullable primary getter.
- [ ] A *Set has no keyed get(string) — that's a Registry.
- [ ] A *Resolver actually does first-match dispatch; predicates composed via anyOf/allOf, not ||/&& chains.
- [ ] A role class does ONE job — no resolution/assembly engine smuggled into a registry/data class.
- [ ] Classification is by marker interface / type, not a const list of class-name strings.
```

## Bad → good

```php
// Bad
public function get(string $key): ?object
{
    return $this->channels[$key] ?? null;
}

// Good
public function resolve(string $key): object
{
    return $this->channels[$key] ?? throw UnknownChannel::forKey($key);
}
```

## When it fires

- A keyed-store `get()` that returns `null` on a miss (should resolve-or-throw) — `NullableRegistryLookupDetector`

## Relationship to the other skills

- [`backend/absence`](../absence/SKILL.md) — a registry `get()` is resolve-or-throw, not an Option; that's the same "missing must-exist thing throws" rule.
- [`backend/exceptions`](../exceptions/SKILL.md) — the named exception a registry throws on a miss (`RegistryEntryNotFoundException::forKey($key)`).
- [`backend/value-objects`](../value-objects/SKILL.md) — these roles are typed structures; reach for one instead of threading a raw `array` keyed store around.
