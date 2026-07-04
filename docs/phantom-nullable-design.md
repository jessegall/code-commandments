# PhantomNullable — design (provenance-first)

**Status:** design + parked v0. The reusable `Ast/Support/TypeResolver` (receiver chain) is
shipped; the detector is NOT — it needs the forward-provenance engine below to be FP-clean.

## The sin

A **phantom nullable** is a field declared `?T` (or `T | null`, `T | Optional | null`) whose value
is, in fact, always present in practice. The type says "this can be missing"; every real use proves
it never is. It's `fix-at-the-source` / `type-honesty`: the null is a lie the declaration tells, and
every consumer pays for it with a re-check of a value that's always there.

The four honest outcomes for a nullable field (the fix ladder):

- **A. Null Object / identity** — the type has an inert value (`new T()`, a `new self(...)` factory).
  → default it non-nullable. *(Already shipped: `NullObjectDefaultScribe` repents `AllNullableData`.)*
- **B. The nullable is a lie** — always supplied, every read assumes presence → make it **required**
  (non-nullable, no default).
- **C. Absence is possible but an error** → **fail hard** (for a Data class, a required field makes
  `::from()` throw on a real miss — "throw instead of carry null").
- **D. Genuinely optional** — some path omits it and readers handle absence → **`Optional`** (or a
  real `?T` where explicit null is meaningful). *Not a finding — this is correct.*

`PhantomNullable` detects **B/C**: a nullable field whose transitive usage never once acknowledges
null.

## Why the shallow version FPs (the calibration finding)

The one-hop version fires on `assume >= 1 && guard == 0`, where a read is `assume` if it is a
un-guarded dereference (`$x->f->m()`) or flows into a non-nullable parameter, and `guard` if it is
null-checked / `?->` / `??` / truthy-tested.

That over-fires because **the deciding guard can live downstream in the value's provenance, not on
the field's own reads.** Real case from `jessegall/workflows`:

```
// ContextInput (a #[Attribute]) declares:
public array | string | null $options = null;

// InputSocket.php:402 — the value flows onward, into another object:
return $class::make(..., options: $declared->options, ...);   // → InputSocket.$options

// InputSocket.php:536 — and THERE it is guarded:
if ($this->options === null) { ... }
```

`ContextInput.$options` is a genuine optional (**D**), but the shallow detector sees only
`resolveOptions($attr->options)` (a non-null sink) and misses that the same value, once flowed into
`InputSocket.$options`, is `=== null` guarded. → false positive.

**Conclusion:** correctness requires following the value's provenance through the whole chain until a
*consumption* point decides nil-vs-non-nil, and treating a downstream guard as a guard on the origin.

## The provenance engine (what to build)

A forward, inter-procedural value-provenance pass. For a nullable field `C::$f`, collect every place
its value can reach and classify the *terminal* consumptions:

**Propagation edges (the value flows on, keep tracing):**
- `$x = $a->f` — the local `$x` now carries the value (assignment).
- `[$a->f]` / `['k' => $a->f]` — carried by the array; trace the array's own reads (`$arr['k']`, a
  `foreach`, spread) — this is the `$a->b[k]->c` shape the value can take.
- `new Y(f2: $a->f)` / `Y::from([... $a->f ...])` — the value becomes `Y::$f2` (or a `from()` field);
  recurse into `Y::$f2`'s provenance. `::from`/`::from<Type>` construct too — part of the chain.
- `fn () => $a->f` / `function () use ($x) {...}` — captured into a closure; trace the closure body.
- `return $a->f` — becomes the caller's value at every call site of this method (join with the return
  type / callers index).

**Terminal consumptions (the chain ends; decide the bucket):**
- **guard** — null-compared, `?->`, `?? `, `isset`/`empty`, truthy-tested (`if ($v)`, `$v && …`,
  `$v ? … : …`, `! $v`). Any one, anywhere in the chain → the field is a legitimate optional. STOP,
  do not flag.
- **assume** — un-guarded dereference, or landed in a non-nullable parameter/typed non-null property.
- **neutral** — passed to a nullable sink, echoed, compared by value.

**Fire** `C::$f` as phantom only when: `assume >= 1` across the whole provenance AND `guard == 0`
anywhere in it. Conservative by construction — a single downstream guard clears it.

### Precision guards (must-haves before shipping)
- **Named arguments** — map a `label: $x->f` arg to the parameter by NAME, not syntactic position
  (the v0 sink check is positional — a bug on named args).
- **Inheritance** — index a field under the class that declares it AND resolve `$this->f` reads in a
  subclass to the base field.
- **Unresolved = unknown, never "assume"** — the resolver is best-effort (~74% of receivers resolve
  today); a read whose receiver/sink can't be typed must be dropped, not counted as presence.
- **Coverage floor** — consider requiring the resolved reads to cover a meaningful fraction of the
  field's uses, or ≥2 independent assume sites, so a lone incidental sink can't flag an optional.

### Calibration gate
Zero false positives on `../workflows` and `../smart-farmers-pos`, every hit read by eye against the
skill — a widespread nullable that's genuinely always-present is a true finding (don't soften), but a
field guarded anywhere in its chain must never be flagged.

## Parked v0 (starting point)

The one-hop detector + its fixtureless unit tests are removed from the tree to keep the suite green;
recover them from git history at the commit that adds this doc (`src/Detectors/Backend/PhantomNullableDetector.php`,
`src/Sins/Backend/PhantomNullable.php`). It already has: the receiver-chain classification, the
truthy/null/`?->`/`??`/isset/empty guard set (calibrated — truthy guards were the first FP found), and
the deref + non-null-sink assume signals. The engine work is to make `assume`/`guard` **provenance-wide**
instead of single-hop, then re-calibrate.
