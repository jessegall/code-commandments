<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\Backend;

use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class RoleVocabulary extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'backend/role-vocabulary',
            tier: Tier::KeepInMind,
            order: 10,
        );
    }

    public function title(): string
    {
        return "Role vocabulary — the name is the contract";
    }

    public function trigger(): string
    {
        return "The three recurring structural roles — Registry (keyed store), Set (membership), Resolver (first-match dispatch) — each with a name and a contract. If a class IS one of these shapes, name it `*Registry`/`*Set`/`*Resolver` and extend the scaffolded base; if it's NAMED one, it must behave like one. Read this BEFORE you hand-roll a keyed store / lookup table, an add-and-iterate collection, or an if/elseif chain that picks the first matching handler — or name a class `*Registry`/`*Set`/`*Resolver`.";
    }

    public function intro(): string
    {
        return "Three shapes recur everywhere: a keyed store, a membership set, a first-match dispatcher. Each has a
name and a contract. Use the name, extend the base, and honour the contract — a `*Registry` that returns
`null`, or a `*Resolver` that doesn't dispatch, is a lie.";
    }

    public function summary(): string
    {
        return "a keyed store / membership set / first-match dispatcher: name it `*Registry`/`*Set`/`*Resolver`, extend the base, honour the contract.";
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
The relationship runs **both ways**:

- **Shape → name.** A class hand-rolling one of these shapes (an array + `register` + lookup; an
  add-and-iterate collection; a first-match `if/elseif` chain) should be **named for the role and extend
  the scaffolded base**, not reinvent the plumbing.
- **Name → shape.** A class *named* `*Registry`/`*Set`/`*Resolver` **must behave like one.** The suffix is
  a promise about the contract; breaking it misleads every reader.

And one cross-cutting rule: **a role class does ONE job.** A `*Registry` that also resolves, queries, or
assembles is hosting a second engine — extract it.

### Registry — a keyed store

`register(key, item)`, `get(key)`, `has(key): bool`, `all()`. Extends the scaffolded `Registry` base; named
`*Registry`.

- **`get($key)` returns the item or THROWS** — never `?T` / `Option` for the primary getter. A miss is a
  broken-state invariant, not a value to branch on (a `find()`/`try*` finder may return `Option`). This is
  the [`absence`](../absence/SKILL.md) resolve-or-throw rule, on the store.
- **Stays a pure store** — no resolution/query/assembly logic living inside it.

### Set — membership + iteration, unkeyed

`add(item)`, `has(item): bool` (identity), `all()`. Extends the scaffolded `Set` base; named `*Set`.

- Total + iterate-only: `has()` returns `bool`, never an Option/nullable leak.
- **No keyed `get(string)`.** If you want to look an item up *by key*, you wanted a **Registry**, not a Set.

### Resolver — first-match dispatch over predicates

A chain that runs predicates and returns the first match's result. Built with `Resolver::firstResultWins(...)`
(or `::collect(...)`), predicates composed from the kernel (`is`, `anyOf`, `allOf`, negation), branches via
`->then($factory)`. Named `*Resolver`; **must actually do first-match dispatch** (else rename).

Compose classifier checks with `anyOf()`/`allOf()`, not a `||`/`&&` chain of `->matches()`.

### Classify by type, not a name list

When a role needs to decide "is this one of mine?", classify from a **marker interface or the AST/type**,
never a hardcoded `const` array of class-name strings. A name list silently rots as classes are renamed or
added; a marker interface is checked by the compiler.
PRINCIPLE;
    }

    public function related(): array
    {
        return [
            Absence::class => "a registry `get()` is resolve-or-throw, not an Option; that's the same \"missing must-exist thing throws\" rule.",
            Exceptions::class => "the named exception a registry throws on a miss (`RegistryEntryNotFoundException::forKey(\$key)`).",
            ValueObjects::class => "these roles are typed structures; reach for one instead of threading a raw `array` keyed store around.",
        ];
    }
}
