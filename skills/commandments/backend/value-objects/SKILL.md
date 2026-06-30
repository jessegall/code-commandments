---
name: value-objects
description: WHEN to give data a type instead of passing it loose — an `array<string,mixed>` bag, 3+ values that always travel together (a data clump), a string-indexed structured array, primitive obsession, or a too-long parameter list all want a typed object. Read this BEFORE you pass or return an untyped array, add another parameter to a crowded signature, or write `$arr['key']` on a structured array. (How to WRITE the class is `spatie-data`; this is when to make one.)
---

# Value objects — give related data a type

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> Data that travels together is a **thing**, not a loose pile of arrays and primitives. The moment a
> cluster of values is passed around, returned, or reached into by string keys, it wants a name and a type.

## The principle

A loose `array` or a fistful of separate parameters has no contract: nothing says which keys exist, what's
required, or what binds the values together — so every reader re-derives it (and gets it slightly wrong).
Wrapping the cluster in a typed object puts the shape, the required fields, and the rules that bind them in
**one place the type enforces**. The data stops being a convention everyone has to remember and becomes a
thing the compiler checks.

This is the *introduce-the-type* decision. Two homes for it:

- **A DTO** (a Spatie `Data` class) — boundary / transfer data: input off a request, an LLM reply, a wire
  payload; output to JSON. Shape-focused, array-constructible. → write it per the
  [`spatie-data`](../spatie-data/SKILL.md) skill.
- **A value object** (`final readonly`, private ctor + a static factory) — a domain concept with
  behaviour and invariants: `Money`, `Email`, `BranchKey`. Identity by value; methods like `equals()`,
  `withX()`, a validated factory.

Both are typed, immutable, named. Pick by role.

## When to introduce a type

Reach for one the moment you are about to:

1. **Pass or return an `array<string,mixed>`** (or any untyped array used as a keyed bag). The keys are an
   undocumented contract — make them a type. (Workflows wraps the genuinely-dynamic case in a typed
   `ValueBag`, not a raw array.)
2. **Thread 3+ values that always travel together** — a *data clump*. If `$nodeId, $x, $y` or
   `$title, $icon, $group` move through call after call as separate args, they are one object wearing three
   parameter slots.
3. **Reach into a structured array by string key** — `$entry['title']`, `$opts['icon']`. String-indexing a
   structured shape is a typed object that hasn't been born yet.
4. **Add a parameter to an already-crowded signature** (roughly 4+). Group the related ones into an object
   instead of growing the list.
5. **Pass a bare primitive that is really a concept** — primitive obsession. A `string $email`, a
   `string $currency` + `int $amount`, a `string $key` with format rules → a value object that owns its
   validation.

## Introduce it at the source

Create the type **where the data is born** — at the boundary that first receives it, the method that first
assembles it — not three frames downstream after it's been threaded around as a bag. A value object
introduced late just relabels data everyone already mis-handled. (This is
[`fix-at-the-source`](../fix-at-the-source/SKILL.md) applied to shape.)

## Checklist

```
Value objects
- [ ] No array<string,mixed> / untyped keyed-bag passed or returned — it's a typed object.
- [ ] No 3+ values threaded together as separate params — they're one object (a data clump).
- [ ] No string-indexing (`$arr['key']`) on a structured array — that's an unborn type.
- [ ] No primitive carrying hidden rules — it's a value object that owns its validation.
- [ ] The type is introduced AT THE SOURCE, not after the loose data has been threaded around.
- [ ] Picked the right home: a Spatie Data DTO (boundary/transfer) vs a final readonly value object (domain concept).
```

## Bad → good

```php
// Bad
public function render(array $breakdown): string
{
    return sprintf(
        'Subtotal %d, tax %d, total %d',
        $breakdown['subtotal'],
        $breakdown['tax'],
        $breakdown['total'],
    );
}

// Good
public function renderTotals(PriceBreakdown $breakdown): string
{
    return sprintf(
        'Subtotal %d, tax %d, total %d',
        $breakdown->subtotal,
        $breakdown->tax,
        $breakdown->total,
    );
}
```

```php
// Bad
public function daily(int $day): array
{
    $currency = config('shop.currency');
    $gross = $this->orders->grossForDay($day);

    return [
        'currency' => $currency,
        'gross' => $gross,
        'net' => (int) round($gross * 0.79),
    ];
}

// Good
public function dailyReport(int $day): DailyReport
{
    $gross = $this->orders->grossForDay($day);

    return new DailyReport(
        gross: $gross,
        net: (int) round($gross * 0.79),
    );
}
```

```php
// Bad
public function record(string $shopId, string $userId, string $channelId): string
{
    return implode(self::SEPARATOR, [$shopId, $userId, $channelId]);
}

// Good
public function recordAccess(AccessContext $context): string
{
    return implode(self::SEPARATOR, [$context->shopId, $context->userId, $context->channelId]);
}
```

```php
// Bad
public function partition(array $rows): array
{
    $valid = [];
    $invalid = [];
    $errors = [];

    foreach ($rows as $row) {
        if ($row === '') {
            $errors[] = 'empty row';
        } elseif (str_contains($row, ';')) {
            $valid[] = $row;
        } else {
            $invalid[] = $row;
        }
    }

    return [$valid, $invalid, $errors];
}

// Good
public function partitionTyped(array $rows): Partitioned
{
    $valid = [];
    $invalid = [];
    $errors = [];

    foreach ($rows as $row) {
        if ($row === '') {
            $errors[] = 'empty row';
        } elseif (str_contains($row, ';')) {
            $valid[] = $row;
        } else {
            $invalid[] = $row;
        }
    }

    return new Partitioned($valid, $invalid, $errors);
}
```

```php
// Bad
public function rates(string $base, array $symbols): array
{
    $query = http_build_query([
        'base' => $base,
        'symbols' => implode(',', $symbols),
    ]);

    return json_decode($this->http->get("https://fx.test/latest?{$query}"), true);
}

// Good
public function ratesTyped(string $base, array $symbols): RateTable
{
    $query = http_build_query([
        'base' => $base,
        'symbols' => implode(',', $symbols),
    ]);

    return RateTable::from(json_decode($this->http->get("https://fx.test/latest?{$query}"), true));
}
```

## When it fires

- String-indexing (`$arr['key']`) a structured array param (an unborn type) — `ArrayBagDetector`
- Returning a multi-field string-keyed array literal (a bag that should be a value object) — `ArrayReturnBagDetector`
- 3+ values threaded as separate params (a data clump → one object) — `DataClumpDetector`
- Returning a positional TUPLE — `return [$node, $key, $inputs, $outputs]` — bundling independent values as a keyless list the caller destructures by position — `PositionalTupleReturnDetector`
- Returning a raw decoded boundary array (`json_decode(...)`) untyped — `RawDecodedArrayReturnDetector`

## Relationship to the other skills

- [`backend/fix-at-the-source`](../fix-at-the-source/SKILL.md) — introduce the type where the data is born, not downstream.
- [`backend/spatie-data`](../spatie-data/SKILL.md) — once you've decided it's a DTO, that skill is *how* to write it (and its honest-field-types rule keeps the new type from being a fresh all-nullable bag).
- [`backend/absence`](../absence/SKILL.md) — the new type's fields still answer "can this be missing?" honestly.
