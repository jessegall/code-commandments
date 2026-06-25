# `UnpackPortResolver` — invariant-architecture refactor (standalone)

Self-contained follow-up to the invariant / root-cause examples pack. Shows the **actual**
`UnpackPortResolver` (`src/Workflow/Nodes/UnpackPortResolver.php`) `initial/` (verbatim, as it ships) →
`final/` (refactored), with the real code copied in — nothing referenced.

```
initial/UnpackPortResolver.php     the real class, unchanged (the "before")
final/UnpackerRegistry.php         refactored + RENAMED (+ isUnpackable / requirePortsFor / requireExtract)
final/NotUnpackableException.php   the added named exception
```

## Context (the rule being applied)

> **`Option<T>` is for absence that may *legitimately* happen. It must NOT be used as a silent stand-in
> for an invariant — a value that, if missing, means the engine is in a broken state. Genuine absence →
> `Option` / a real default. Invariant violation → fail loud (`getOrThrow` / a named exception).**

The smell to kill is an invariant violation laundered into "nothing" — a `getOr(null)`/`getOr(false)`, or
a `->tap()` that runs on `Some` and **silently does nothing on `None`**, where the `None` can only mean a
bug. The fix is a throwing companion (`...OrFail`/`require...`), keeping a tolerant `Option` query
alongside it for the cases where absence is real.

## Verdict: this class is already ~80% right — refactor, don't rewrite

**Keep (already correct):**
- `register()` / `registerMany()` already **throw** (`InvalidUnpackerException`) on a bad/duplicate
  unpacker. Invariants enforced. ✅
- `portsFor(): Option` / `extract(): Option` returning **none for an opaque type** is **genuine absence**
  (an Unpack node can be wired to a scalar/opaque type with no field ports). ✅ keep `Option`.
- `reflectedSocketType()->getOr(WireType::MIXED)` — a **real default** (untyped/union property). ✅ legit.
- `portCountFor()->getOr(T_Array::EMPTY)` then `count()` — `0` is a **valid answer**. ✅ legit.

**The one real smell is at the runtime call sites**, where `extract()` is consumed with `->tap()` even
though the value was already validated as unpackable (a `item.<field>` / `port.<field>` consumer is wired).
`tap` drops the `None` silently → the node produces no fields instead of failing loud:

```php
// src/Workflow/Nodes/Control/ForEachNode/ForEachNode.php  (verbatim)
        if (! is_object($item))
        {
            return;
        }

        $this->unpackRegistry->extract($item)->tap(function (array $fields) use ($run, $nodeId)
        {
            foreach ($fields as $name => $value)
            {
                $run->set($nodeId, "item.{$name}", $value);
            }
        });
```

```php
// src/Workflow/Compilation/Pipes/BridgeToTypedContextPipe.php  (verbatim)
        if ($this->unpackRegistry === null || ! is_object($value))
        {
            return;
        }

        $this->unpackRegistry->extract($value)->tap(function (array $fields) use ($context, $portName, &$written)
        {
            foreach ($fields as $field => $fieldValue)
            {
                $context->set($this->nodeId, "{$portName}.{$field}", $fieldValue);
                $written["{$portName}.{$field}"] = true;
            }
        });
```

## The refactor (see `final/`)

### 1. Keep the `Option` queries, add invariant companions + a `has()`
```php
/** has() companion — "can this be unpacked?" (ask) */
public function isUnpackable(string $class): bool
{
    return $this->portsFor($class)->hasValue();
}

/** invariant variant — the class MUST be unpackable here (post-validation) */
public function requirePortsFor(string $class): array
{
    return $this->portsFor($class)
        ->getOrThrow(fn (): NotUnpackableException => NotUnpackableException::forClass($class));
}

/** invariant variant — the value MUST be unpackable here (a field port is wired) */
public function requireExtract(object $instance): array
{
    return $this->extract($instance)
        ->getOrThrow(fn (): NotUnpackableException => NotUnpackableException::forClass($instance::class));
}
```

### 2. ADD `NotUnpackableException` (named, static factory, same namespace as `InvalidUnpackerException`).

### 3. RENAME the class `UnpackPortResolver` → `UnpackerRegistry`, and prefer the existing base
See **"Naming / identity"** below. This is the one *non-additive* change (a rename touches every call
site + the service-provider binding), so do it as its own commit.

### 4. Route the runtime `tap` sites to fail loud *when fields are required*
`tap` (do-nothing-on-None) is fine only when nothing downstream needs the fields. When a field port is
wired, use `requireExtract()` so a non-unpackable value throws instead of vanishing:

```php
// ForEachNode — after (property standardized to $unpackers, see Naming below)
if ($this->bodyConsumesItemFields()) {                 // a wired item.<field> exists → required
    foreach ($this->unpackers->requireExtract($item) as $name => $value) {
        $run->set($nodeId, "item.{$name}", $value);
    }
    return;
}
$this->unpackers->extract($item)->tap(/* best-effort: nobody wired item.<field> */);
```

`BridgeToTypedContextPipe` already has the seam — its `&$written` tracker knows whether the wired
`portName.<field>` ports got values. If the port declares field consumers but `extract` yielded `None`
(nothing written), that is the invariant violation → `requireExtract()` (or assert `$written` is
non-empty). The `bodyConsumesItemFields()` / "declares field consumers" predicate comes from the compiled
descriptor (which `item.<field>` / `port.<field>` outputs are actually wired); shown abstractly — the point
is *only fail loud where the fields are required.*

## Decision table

| Member | Absence means | Decision |
|---|---|---|
| `register` / `registerMany` | wiring bug | throw — **unchanged** ✅ |
| `portsFor(): Option` | opaque type (valid) | keep `Option` |
| `extract(): Option` | opaque object (valid) | keep `Option` |
| `reflectedSocketType()->getOr(MIXED)` | no single wire type | keep — **legit default** |
| `portCountFor()->getOr(EMPTY)` | 0 ports (valid) | keep — **legit default** |
| `all()` | — | total, unchanged |
| **new** `isUnpackable()` | — | has() companion |
| **new** `requirePortsFor()` / `requireExtract()` | post-validation miss = bug | **throw** |
| runtime `extract()->tap()` (ForEach / Bridge) | required field value missing | **fail loud** via `requireExtract()` |

## Naming / identity — this is a Registry

The class was `UnpackPortResolver`, but the codebase already disagreed with that name — **one class, five
caller names**: `$unpackRegistry` (×4: `UnpackExpander`, `ForEachNode`, `BridgeToTypedContextPipe`,
`PipeEmitter`), `$unpacks` (`WorkflowEngine`), `$registry` (the service provider), `$ports`
(`UnpackerListCommand`). The plurality already calls it `unpackRegistry`.

### When to name a class `*Registry`
Name it `*Registry` when **all three** hold — and the dead giveaway is the first:

1. **You `register()` into it** (`register()` / `registerMany()`) — *you put things in.* ← this is the tell.
2. It **owns a keyed store** of those things.
3. It **answers membership/lookup** over them — the trio `find()` (→ `Option`) · `has()` · `get()` (→ throws).

If you only have (2)+(3) it's a `*Map` / `*Catalog`. If you compute/derive on demand without owning a store
it's a `*Resolver` / `*Factory`. This class does **all three** (`register`/`registerMany` + the
`$unpackers`/`$portsByClass` store + `portsFor`/`isUnpackable`/`requirePortsFor`) → **Registry**. The
invariant refactor above literally gave it the canonical find/has/get trio, which *is* the registry
contract — confirming the identity.

### Prefer the base that already exists (don't hand-roll it)
The workflows package already ships **`JesseGall\Workflows\Support\Registry`** — an abstract base with
`register` / `registerMany` / `all` / `values` / `find(): Option` / `get(): T` (throws
`RegistryMissException`) / `first()`. **Four** registries already extend it: `ResourceRegistry`,
`NodeDescriptorRegistry`, `TriggerRegistry`, `SchemaTypeRegistry`. `UnpackPortResolver` is the **one
registry that hand-rolled all of it** — which is also why the commandments `RegistryReturnContractProphet`
(marker-driven: it only fires on a class named/extending `Registry`, a `Registry` interface, or
`#[Registry]`) never caught it.

So the deeper, preferred shape:
- model the **unpacker map** as the base `Registry` (`register`/`all`/`find`/`get` come for free, and the
  base's throwing `get()` *is* the invariant variant — no bespoke `requirePortsFor` needed for that map);
- keep `portsFor` / `extract` / `requireExtract` as the **resolver layer** on top (they also serve
  *unregistered* classes — models, plain objects via reflection — so they aren't pure registry lookups).

*(Caveat: the base `register(string $key, mixed $item)` is 2-arg keyed; this class's `register(string
$unpackerClass)` derives its key via `::forClass()` and also builds the `$portsByClass` cache — so it's a
registry-**backed resolver**, not a drop-in subclass. Align where it fits; the README's `final/` keeps the
bespoke shape but adds the find/has/get trio so it at least matches the base's contract.)*

### This belongs in the commandments package too (advisory side-note)
`RegistryReturnContractProphet` deliberately skips the "looks like a registry but isn't marked" heuristic
(false-positive risk). That leaves a gap this class fell through. Worth adding as a **low-tier advisory
side-note** (a suggestion, never a sin):

- **"You `register()` here → this looks like a `Registry`."** When a class has `register()`/`registerMany()`
  + a keyed store + find/has/get but **isn't** marked, suggest naming it `*Registry` and **extending the
  base** (after which `RegistryReturnContractProphet` enforces the return contract).
- **Suggest extracting/using a shared base** when ≥2 registry-shaped classes hand-roll the same
  register/find/get — and **scaffold it**. Commandments already has `commandments:scaffold` +
  `stubs/scaffold/*.stub` (it emits `Option`, `Resolver`, … into `App\Support`) but **no `Registry.stub`**;
  adding one lets `commandments:scaffold` generate an abstract `Registry` base, exactly like the workflows
  `Support\Registry`. The marker-driven prophet's own docblock already endorses "one abstract base marked
  once, with N concrete subclasses" — this just gives users a one-command way to create that base.

## Class changes
- **ADD** `NotUnpackableException` (named, static factory).
- **RENAME** `UnpackPortResolver` → `UnpackerRegistry`, and **standardize the five caller property names**
  to one (e.g. `$unpackers`). This is the only non-additive change (rename + binding).
- **Prefer** the existing `Support\Registry` base for the unpacker map (see above); no class removed.

Everything else is additive, so the bulk of the refactor stays a safe, incremental change.
