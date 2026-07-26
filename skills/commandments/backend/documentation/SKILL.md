---
name: commandments-backend-documentation
description: How to document — and mostly NOT. Docblocks are 1–2 lines (3 max), present-tense, about the code as it is NOW; inline comments are RARE and only ever explain a non-obvious *why*; NEVER narrate the past or a change ("previously…", "used to…", "now we…", "refactored to…"). Read this the MOMENT you are about to write a docblock (`/**`), an inline comment (`//`), or a class/method description.
---

# Documentation — concise, present-tense, rare

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> A docblock describes the **code as it is**, in as few words as possible — write one. An inline comment
> is a last resort. Neither is a changelog, a tutorial, or a story about the refactor. Most code needs no
> inline comment at all.

## The principle

A docblock and a comment are not free: every line a reader must scan is a tax on understanding, and a
line that restates the code, or narrates how it got here, is pure tax with no return. The bar is high —
write a doc only when it tells the reader something the code itself does not.

Docs are still **wanted**, not banned. A short docblock on a class or method is good and expected: one
sentence saying what it *is* or *does*, plus the `@param` / `@return` / `@throws` type contract. Keep it to
a line or two, present-tense, about the code as it is now — never *how* it works internally, *why* it
changed, or what it *used to* be. Git holds the history; when you replace code you replace it, you don't
annotate the grave.

Inline comments are the rarest of all — default to none. The code already says *what* it does; the only
comment worth writing explains a non-obvious **why** the code can't: a hidden invariant, a workaround for an
external bug, a constraint the reader can't infer. (A structural section divider in a large class is fine
if the codebase already uses them — structural, not narrative.) Everything else: don't write it.

## Rules

- Comment what the code IS now, never its history — no "formerly/used to be/refactored/no longer an X" archaeology; git holds the past.
- Keep a class docblock to one tight paragraph — a multi-paragraph essay means the class does too much.
- A docblock must add meaning beyond the signature — drop `@param Type $x` lines that only restate an already-typed parameter.
- A `{@see}`/`{@link}` must resolve to a real class. A cross-reference to a first-party class the codebase no longer declares is stale documentation — repoint it at the current class or delete it. (References into another vendor namespace are left alone; they can't be verified here.)
- State what the code IS, affirmatively — a comment that defends it against a strawman (that it is "not random", "no magic", "not a typo") is negative space; make the code self-evident and delete the comment.
- An inline comment must say something the code does not — never narrate the statement below it; if every word of the comment is already a word of the code, delete the comment.

## Bad → good

```php
// Bad
public function search(array $filters): array
{
    $perPage = config('shop.catalog.per_page');

    // formerly filtered in PHP; refactored into the query builder in v3
    $term = $filters['q'];
    $sort = $filters['sort'];

    return $this->run($term, $sort, $perPage);
}

// Good
public function searchSorted(string $term): array
{
    // the cached rank may no longer exist; the flag previously bound is used to scope the column
    $sort = $term === '' ? 'rank' : 'relevance';

    return $this->run($term, $sort, $this->settings->perPage);
}
```

```php
// Bad
final class LegacyOrderImporter
{
    // previously this returned an array, now it returns a Customer or null
    public function findCustomer(string $email): ?Customer
    {
        // loop over all customers and find the matching one
        return Customer::query()->where('email', $email)->first();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function import(array $rows): void
    {
        foreach ($rows as $row) {
            $customer = $this->findCustomer($row['email'] ?? '');

            // changed from update() to direct assignment in v2
            if ($customer !== null) {
                $customer->imported = true;
                $customer->save();
            }
        }
    }

    public function emailKnown(string $email): bool
    {
        return $this->findCustomer($email)?->exists ?? false;
    }
}

// Good
final class TidyOrderImporter
{
    public function import(string $email): void
    {
        Customer::query()->where('email', $email)->firstOrFail();
    }
}
```

```php
// Bad
public function award(int $points, string $name): string
{
    return $name . ':' . $points;
}

// Good
public function awardLabel(int $points, string $name): string
{
    return $name . ':' . $points;
}
```

```php
// Bad
final class ReviewDoc
{
    public function approve(int $stars, bool $verified): bool
    {
        return $verified && $stars >= 3;
    }

    public function flagged(string $body): bool
    {
        return str_contains($body, 'spam');
    }

    public function digest(int $count): string
    {
        return $count === 1 ? '1 review' : "{$count} reviews";
    }
}

// Good
final class HonestDoc
{
    public function tally(int $items): int
    {
        return max(0, $items);
    }
}
```

```php
// Bad
public function get(string $code): SkuEntry
{
    // no magic here; a missing code yields an empty entry
    return new SkuEntry();
}

// Good
public function has(string $code): bool
{
    // a random probe key still reads cleanly through the same path
    return $code !== '';
}
```

```php
// Bad
public function load(int $cartId): void
{
    // store the cart snapshot
    $this->snapshot = CartSnapshot::of($cartId);
}

// Good
public function apply(string $coupon): void
{
    // a coupon may be presented twice; the desk keeps both, and the till reconciles them later
    if ($this->honours($coupon)) {
        $this->applied[] = $coupon;
    }
}
```

## When it fires

- History/archaeology comments ("formerly / used to be / refactored / no longer an X / was extracted") — `ArchaeologyCommentDetector`
- Multi-paragraph class docblock (class too big) — `BloatedDocblockDetector`
- Docblock that only restates the typed signature (`@param Type $x`, no description) — `CeremonyDocblockDetector`
- A docblock `{@see}`/`{@link}` cross-references a FIRST-PARTY class that does not exist in the codebase — documentation pointing at a name that was renamed or removed, never at what the code actually is — `DanglingDocReferenceDetector`
- A comment defending the code against a strawman ("not random", "no magic", "not a coincidence", "not dead code") — `NegativeSpaceCommentDetector`
- An inline comment that only spells the statement below it back in prose ("// save the order" over `$this->orders->save($order)`) — `RestatedCommentDetector`

## Checklist

- [ ] Comment what the code IS now, never its history — no "formerly/used to be/refactored/no longer an X" archaeology; git holds the past.
- [ ] Keep a class docblock to one tight paragraph — a multi-paragraph essay means the class does too much.
- [ ] A docblock must add meaning beyond the signature — drop `@param Type $x` lines that only restate an already-typed parameter.
- [ ] A `{@see}`/`{@link}` must resolve to a real class. A cross-reference to a first-party class the codebase no longer declares is stale documentation — repoint it at the current class or delete it. (References into another vendor namespace are left alone; they can't be verified here.)
- [ ] State what the code IS, affirmatively — a comment that defends it against a strawman (that it is "not random", "no magic", "not a typo") is negative space; make the code self-evident and delete the comment.
- [ ] An inline comment must say something the code does not — never narrate the statement below it; if every word of the comment is already a word of the code, delete the comment.

## Related skills

- [`backend/fix-at-the-source`](../fix-at-the-source/SKILL.md) — fix the shape instead of documenting the workaround. A doc should never be the thing keeping a confusing design legible.
