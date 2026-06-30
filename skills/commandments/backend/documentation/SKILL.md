---
name: documentation
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

- Comment what the code IS now, never its history — no "previously/used to/changed from" or task-ref archaeology.
- Keep a class docblock to one tight paragraph — a multi-paragraph essay means the class does too much.
- A docblock must add meaning beyond the signature — drop `@param Type $x` lines that only restate an already-typed parameter.

## Bad → good

```php
// Bad
public function search(array $filters): array
{
    $perPage = config('shop.catalog.per_page');

    // used to filter in PHP, moved to the query builder in v3
    $term = $filters['q'];
    $sort = $filters['sort'];

    return $this->run($term, $sort, $perPage);
}

// Good
public function searchSorted(string $term): array
{
    // map the public sort flag to the indexed column
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

## When it fires

- History/archaeology comments ("previously / used to / refactored / changed from", task refs) — `ArchaeologyCommentDetector`
- Multi-paragraph class docblock (class too big) — `BloatedDocblockDetector`
- Docblock that only restates the typed signature (`@param Type $x`, no description) — `CeremonyDocblockDetector`

## Checklist

- [ ] Comment what the code IS now, never its history — no "previously/used to/changed from" or task-ref archaeology.
- [ ] Keep a class docblock to one tight paragraph — a multi-paragraph essay means the class does too much.
- [ ] A docblock must add meaning beyond the signature — drop `@param Type $x` lines that only restate an already-typed parameter.

## Related skills

- [`backend/fix-at-the-source`](../fix-at-the-source/SKILL.md) — fix the shape instead of documenting the workaround. A doc should never be the thing keeping a confusing design legible.
