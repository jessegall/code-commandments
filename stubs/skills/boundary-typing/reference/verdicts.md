# BoundaryTyping verdicts — bad → good

The `TypeHonesty` prophet emits at most one disjoint verdict per site, coarse →
fine. Each below: what fires, the dishonest shape, and the honest fix. The
assert-vs-declare-optional-vs-absolve decision tree lives in `SKILL.md`.

---

## V1 — FAKE-REQUIRED (sin)

**Fires:** a `::from([...])` item `<key> => <expr> ?? <empty string>` (`''`,
`T_String::EMPTY`, `T_String::empty()`) whose key maps to a constructor param that
is non-nullable, has no default, and is typed `string`.

```php
// Bad — a fake identity manufactured to satisfy a required slot
return AssistantUpdateNodeAction::from([
    'id'      => $raw->id ?? '',                   // V1
    'summary' => $raw->summary ?? T_String::empty(),
    'nodeId'  => $raw->nodeId,
]);

// Good — assert the required value; make the optional field honest
if ($raw->id === null || $raw->nodeId === null) {
    throw UnusableActionException::missingIdentity();
}
return AssistantUpdateNodeAction::from([
    'id'      => $raw->id,
    'summary' => $raw->summary,                    // action declares ?string $summary
    'nodeId'  => $raw->nodeId,
]);
```

Only **string** empties fire — an empty `[]` / `0` / `false` is a legitimate value
whose required-ness is judged by use, not flagged here.

## V2 — PHANTOM-NULLABLE (warn)

**Fires:** a class extending a boundary base (`Spatie\Data` / `FormRequest`) whose
**every** field (≥2) is `?T = null` — *and* a consumer treats at least one field as
a required value (deref / coalesce-to-non-null / cast / call-arg / foreach), not
merely branches on its null (the V2-REFINE use-following gate).

```php
// Bad — validates nothing; every required-field check is pushed downstream
final class RawAction extends Data {
    public function __construct(
        public readonly ?string $id = null,
        public readonly ?string $name = null,
    ) {}
}
```

**Good:** make the fields that are actually required non-nullable, so `from()`
throws on a missing value. If the DTO is genuinely a tolerant boundary (every field
is an alternative / optional — e.g. an untrusted wire frame), it is a legitimate
exception: absolve it (audited).

## V3 — DTO-OR-ARRAY-SEAM (warn)

**Fires:** a private/protected method param or return typed `T|array` where T
resolves to a project Data/boundary class. The blessed `Arrayable|array` and
public methods are excluded.

A `T|array` seam re-hydrates on every call. **Good:** hydrate once at the boundary;
the seam takes `T`.

## V4 — MIXED-SEAM (warn)

**Fires:** a private/protected param typed exactly `mixed`/`object` where every
resolved caller passes the same single concrete project type (unanimous; bails on
any unresolved/scalar/differing arg).

**Good:** type the seam to the concrete class that flows through it.

## V5 — REQUIRED-BUT-NULLABLE (sin)

**Fires:** a boundary DTO field typed `?T`/`T|null` that the class's own `rules()`
marks unconditionally `required` (bare `required` — not `required_if`, not
alongside `nullable`), or carries a `#[Required]` attribute.

```php
// Bad — the type says optional, the rules say required: pick one
public function __construct(public readonly ?string $email = null) {}
public function rules(): array { return ['email' => ['required', 'email']]; }

// Good — the type agrees with the contract
public function __construct(public readonly string $email) {}
```

## V6 — BOOL-UNION (warn)

**Fires:** a `T|false` union (literal `false`, exactly 2 members, T a class) used to
encode found-or-not. `Closure` (callable poly-form) and `*Response`/`Responsable`
(framework render/defer contract) are excluded.

```php
// Bad — found-or-not smuggled as T|false
public function find(string $id): User|false

// Good — model presence in the type
public function find(string $id): Option /* <User> */
```

## V7 — NONNULL-GUARD (warn)

**Fires:** a `=== null` / `!== null` / `is_null()` guard on a value whose declared
type is **non-nullable** (the `NoCoalesceOnNonNullable` twin). `empty()` / `assert`
are deliberately excluded — falsiness checks are legitimate on non-nullables.

```php
// Bad — the type already excludes null; the guard is dead
function send(User $user): void {
    if ($user === null) { return; }   // $user can never be null
    // ...
}
```

**Good:** drop the dead guard. If the value can really be absent, the *type* is
wrong — make it `?User` and handle the absence honestly.

## V8 — DISCRIMINATED-PUNT (warn)

**Fires:** a boundary DTO with a `mixed` payload field + a string/enum
discriminator, where a consumer `match`/`switch`-es on the discriminator off a
provably-typed receiver AND reads the `mixed` payload inside each arm — an untyped
tagged-union.

**Good:** model each variant as its own typed shape (a sealed hierarchy / per-case
DTO) so the payload is typed per discriminator value, not re-coerced at every
consumer.
