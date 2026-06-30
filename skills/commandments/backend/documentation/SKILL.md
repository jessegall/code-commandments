---
name: documentation
description: How to document — and mostly NOT. Docblocks are 1–2 lines (3 max), present-tense, about the code as it is NOW; inline comments are RARE and only ever explain a non-obvious *why*; NEVER narrate the past or a change ("previously…", "used to…", "now we…", "refactored to…"). Read this the MOMENT you are about to write a docblock (`/**`), an inline comment (`//`), or a class/method description.
---

# Documentation — concise, present-tense, rare

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> A docblock describes the **code as it is**, in as few words as possible — write one. An inline comment
> is a last resort. Neither is a changelog, a tutorial, or a story about the refactor. Most code needs no
> inline comment at all.

## This fires the moment you type `/**` or `//`

Before you write a single doc or comment, check it against the four rules below. If it doesn't pass,
**don't write it.**

## The rules

1. **Method and class docblocks are fine — and expected. Write one.** A short docblock on a class or
   method is good and welcome; the conciseness rule is not "don't write docs". But it **MUST** follow the
   rest of this rule: **1–2 lines (3 only if truly needed), present-tense, about the code now** — one
   sentence saying what the class/method *is* or *does*, plus `@param` / `@return` / `@throws` for the type
   contract. Nothing about *how* it works internally, *why it was changed*, or what it *used to* be.

2. **Inline comments are RARE — default to none.** Write one only when the *why* is non-obvious: a hidden
   invariant, a workaround, an external constraint. **Never restate *what* the code does** — the code
   already says that.

3. **NEVER document the past.** No `// previously…`, `// changed from…`, `// used to be…`, `// now we…`,
   `// refactored to…`, no plan-phase or task references. A comment describes the present code; **git holds
   the history.** When you replace code, you replace it — you don't annotate the grave.

4. **No long class docblocks.** One sentence: what the class is. If you need a paragraph to explain it, the
   class is doing too much — fix that, don't document around it.

## The only comments worth writing

- **A non-obvious *why*** — a hidden invariant, a workaround for an external bug, a constraint the reader
  can't infer from the code.
- A **structural section divider** in a large class, if the codebase already uses them
  (`// ----------[ Section ]----------`). Structural, not narrative.

Everything else: delete it, or don't write it.

## Checklist

```
Documentation
- [ ] Docblock is 1–2 lines, present-tense, about what the code IS/does now (+ @param/@return/@throws).
- [ ] No history: no "previously / used to / now we / changed / refactored", no task/phase references.
- [ ] No inline comment that restates what the code already says.
- [ ] Any inline comment that survives explains a non-obvious WHY (or is a structural divider).
- [ ] No multi-paragraph class docblock (if it needs one, the class is too big).
```

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

## Relationship to the other skills

- [`backend/fix-at-the-source`](../fix-at-the-source/SKILL.md) — fix the shape instead of documenting the workaround. A doc should never be the thing keeping a confusing design legible.
