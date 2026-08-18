---
name: commandments-backend-value-objects
description: "WHEN to give data a type instead of passing it loose — an `array<string,mixed>` bag, 3+ values that always travel together (a data clump), a string-indexed structured array, primitive obsession, or a too-long parameter list all want a typed object. Read this BEFORE you pass or return an untyped array, add another parameter to a crowded signature, or write `$arr['key']` on a structured array. (How to WRITE the class is `spatie-data`; this is when to make one.)"
---

# Value objects — give related data a type

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> Data that travels together is a **thing**, not a loose pile of arrays and primitives. The moment a
> cluster of values is passed around, returned, or reached into by string keys, it wants a name and a type.

## The principle

Data that travels together is a **thing**, not a loose pile of arrays and primitives. The moment a cluster
of values is passed around, returned, or reached into by string keys, it wants a name and a type — the type
IS the documentation, the validation, and the contract, all enforced by the compiler instead of by every
reader's memory.

Reach for a type the moment you are about to: pass or return an `array<string, mixed>` keyed bag (its keys
are an undocumented contract — make them a type); thread three-or-more values that always travel together —
a *data clump* wearing separate parameter slots; reach into a structured array by string key
(`$entry['title']`) — a typed object that hasn't been born yet; grow an already-crowded signature (group
the related arguments into one object instead of adding the fourth); or pass a bare primitive that is really
a concept — a `string $email`, a `string $currency` + `int $amount`, a `string $key` with format rules → a
value object that owns its own validation.

Introduce the type **where the data is born** — at the boundary that first receives it, the method that
first assembles it — not three frames downstream after it has been threaded around as a bag. A value object
introduced late just relabels data everyone already mishandled. This is fix-at-the-source applied to shape.

## Rules

- [ ] Give a structured array a typed value object — never read a named field by string key off an `array` param.
      _A Spatie `Data` object built via `::from($array)`._
- [ ] Return a typed value object, not a multi-field string-keyed array literal.
      _Return a Spatie `Data` object via `::from(...)`._
- [ ] Fields that move as a unit are one type: extract the clump into a value object and hold THAT; never mirror a datum that already lives on a nested object.
      _Fold the co-moving fields into one value object (name the existing type when the clump already is one); drop a field that duplicates a nested object's property._
- [ ] Bundle values that always travel together into one object; don't thread 3+ of them as separate params.
      _A value object the params fold into (`Money::of()`, `NodePosition`)._
- [ ] A wither changes ONE thing: say only what changes. `clone($this, ['x' => $x])` states the intent; re-listing every field states the constructor again, N times over.
      _Replace `new self($this->a, $this->b, $changed)` with `clone($this, ['c' => $changed])` — `repent` does it for you._
- [ ] Make a value immutable: build it complete and derive a NEW one to change it; never write its fields after construction.
      _`readonly` on the class, and a `with…()`/named derivation that returns a new instance (PHP 8.5's `clone with`)._
- [ ] Return a typed object, not a positional tuple `[$a, $b, $c]` the caller destructures by position.
      _A small `readonly` result object._
- [ ] Return a typed object from a decoded boundary; never hand back a raw `json_decode(...)` array.
      _Decode into a Spatie `Data` object: `X::from(json_decode(...))`._
- [ ] When scalar fields on a Data class share a prefix that names a value object the codebase already declares, they restate that object flat. Nest them into the existing sub-object and shed the prefix — `wireType`/`wireLabel` become `wire: Wire{type, label}`.
      _Replace the prefixed siblings with a single nested property typed as the existing value object, dropping the prefix from each member._

## Worked example

### array-bag

String-indexing (`$arr['key']`) a structured array param (an unborn type)

```php
----------[ Bad ]----------

public function normalize(array $row): void
{
    $this->products->upsert(
        $row['sku'] ?? '',
        $row['name'] ?? '',
        (int) ($row['stock'] ?? 0),
    );
}

----------[ Good ]----------

// The same import row, given its type the moment it arrives: `ImportRow::from($row)` names the
// fields ONCE at the boundary, so nothing downstream reads `$row['sku']` off a loose array.

public function ingest(array $row): void
{
    $this->persist(ImportRow::from($row));
}
```

The other 8 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `vendor/bin/commandments judge --skill=backend/value-objects` — find every one of these in the codebase.
- `vendor/bin/commandments info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `array-bag`, `array-return-bag`, `coupled-fields`, `data-clump`, `hand-rolled-wither`, `mutable-value-object`, `positional-tuple-return`, `raw-decoded-array-return`, `flat-field-cluster`.
- `vendor/bin/commandments repent --sin=<sin>` — auto-fix, for `hand-rolled-wither`. Review it with `--dry-run` first.
- `vendor/bin/commandments report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 9 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`backend/fix-at-the-source`](../fix-at-the-source/SKILL.md) — introduce the type where the data is born, not downstream.
- [`backend/spatie-data`](../spatie-data/SKILL.md) — once you've decided it's a DTO, that skill is *how* to write it (and its honest-field-types rule keeps the new type from being a fresh all-nullable bag).
- [`backend/absence`](../absence/SKILL.md) — the new type's fields still answer "can this be missing?" honestly.
