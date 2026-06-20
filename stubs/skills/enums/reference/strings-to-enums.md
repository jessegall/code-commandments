# Strings to enums

Closed-set values — directions, statuses, kinds, modes, types, roles — belong in
enums, not string literals. Stringly-typed values bypass static analysis, IDE
refactors, and exhaustive `match`; every consumer re-validates the value by hand.

There are two signals that say "this string is an enum in disguise": a **literal**
(a default, a named arg, a closed set of call-site values, a `match`/`switch`/`if`
on string literals) and a **field NAME** that reads as a closed set even with no
literal present. Both lead to the same fix.

---

## Bad → good

### A closed-set field typed `string`

The name alone is signal enough — `direction`, `status`, `kind`, `mode`, `type`,
`role`, … with a finite, known set of values.

```php
// BAD — invisible to every refactor; every reader re-validates
class NodeSocketData extends Data
{
    public function __construct(
        public string $direction,   // 'input' | 'output' — a closed set as a string
    ) {}
}
```

```php
// GOOD — a purpose-specific enum; the type IS the constraint
enum SocketDirection: string
{
    case Input = 'input';
    case Output = 'output';
}

class NodeSocketData extends Data
{
    public function __construct(
        public SocketDirection $direction,
    ) {}
}
```

On a Spatie `Data` class, `::from()` bridges string↔enum at the boundary, so
construction and serialization keep working untouched.

### A string literal where an enum case belongs

```php
// BAD
new Port(direction: 'input');
public function __construct(public readonly string $status = 'running') {}
```

```php
// GOOD
new Port(direction: PortDirection::Input);
public function __construct(public readonly WorkflowRunStatus $status = WorkflowRunStatus::Running) {}
```

### A closed set of call-site values on a `string` param

```php
// BAD — the param is stringly-typed, but the call sites form a tiny closed set
public function fanout(string $verb): void { /* … */ }

$this->fanout('publish');
$this->fanout('unpublish');
```

```php
// GOOD — retype the param to the enum the call-site values describe
public function fanout(MirroringAction $verb): void { /* … */ }
```

### Branching on string literals

A `match` / `switch` / `if`-`elseif` whose labels are all string literals of one
enum is an enum dispatch wearing a costume:

```php
// BAD
match ($port->kind) {
    'input'  => …,
    'output' => …,
};
```

```php
// GOOD — type the subject as the enum and branch on its cases
match ($port->kind) {
    PortKind::Input  => …,
    PortKind::Output => …,
};
```

### A closed-set membership test

```php
// BAD — a one-of test rebuilt from raw strings
in_array($type, ['string', 'int', 'float', 'bool'], true)
```

```php
// GOOD — every value is a FieldType case; route through CompareSelf::equalsAny
FieldType::equalsAny($type, FieldType::String, FieldType::Int, FieldType::Float, FieldType::Bool)
```

(See `reference/behavioural-dispatch.md` for the CompareSelf family.)

---

## Reuse an existing enum, or create a new one?

Shared values are **NOT** the same type. Two enums can both spell `'active'` /
`'archived'` and still mean different things; reusing the wrong one couples
unrelated concerns and breaks the moment one set evolves.

| Situation | Do |
|---|---|
| The field IS that concept — its value already COMES FROM that enum (`Field::$type` ← a `SchemaFieldType`; a `$role` hydrated from an `AiRole`) | **Reuse** the existing enum. The strongest tell is the source: if a value is produced by something already typed `EnumX`, the field is an `EnumX`. |
| A finite set that is its OWN concept (a socket `$direction` of input/output is not a sort `Direction` of asc/desc) | **Create** a new, purpose-specific enum named for THIS field's concept. This is the default. |
| Values happen to coincide with an existing enum but the concept differs | **Create** a new one — coincidental overlap is not identity. |
| The value is genuinely open free text that merely shares the name (a `$type` holding an arbitrary MIME string, a `$format` holding a user pattern) | **Leave it** a `string`. |

The matched enum a prophet names is the closest EXISTING one — a *candidate*, not
a requirement. The fix is always "make this a typed enum value", not "reuse that
specific enum".

---

## When to reach for it

- A `string` / `?string` field whose name ends in a closed-set noun at a word
  boundary (`sortDirection`, `node_type`) and whose values are a known finite set.
- A string literal default, named arg, or closed set of call-site values that
  are the cases of one concept.
- A `match` / `switch` / `if` chain or an `in_array(...)` set whose labels are
  string literals of one enum.

## When to leave it

- The value is genuinely **open** free text that merely shares the name — an
  arbitrary MIME string in `$type`, a user-supplied pattern in `$format`.
- A wire-format boundary: literals inside `toArray`, `jsonSerialize`, `render`,
  or a `JsonResource` / `Resource` / `Response` class are the public contract —
  the string IS the API there. Left alone.
- A named arg to a `vendor/` class / static / attribute you can't change — the
  consumer cannot retype a third-party signature.
- The field carries a hydration attribute (`#[Input]`, `#[Pick*]`, a container
  binding) that hands it a raw string regardless of declared type — an enum
  retype would `TypeError`.

## Enforced by

`StringsThatShouldBeEnums` (literal-anchored: default / named arg / call-site set
/ `match`-`switch`-`if` / `in_array` set) and `PreferEnumForClosedSetField`
(name-anchored: a `string` field whose name denotes a closed set — advisory, and
auto-fixable on Spatie Data with `repent --input create-enum-class=… --input cases=…`).

```
commandments:scripture --prophet=StringsThatShouldBeEnums
commandments:scripture --prophet=PreferEnumForClosedSetField
```
