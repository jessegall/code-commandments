---
name: documentation
description: How to document — and mostly NOT. Docblocks are 1–2 lines (3 max), present-tense, about the code as it is NOW; inline comments are RARE and only ever explain a non-obvious *why*; NEVER narrate the past or a change ("previously…", "used to…", "now we…", "refactored to…"). Read this the MOMENT you are about to write a docblock (`/**`), an inline comment (`//`), or a class/method description.
---

# Documentation — concise, present-tense, rare

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

## Bad → good

```php
// Bad — narrates the change; pure noise
// Previously this hydrated a RawAssistantAction; now we parse the array directly.
$id = $entry->string('id');

// Good
$id = $entry->string('id');
```

```php
// Bad — a class docblock telling a story
/**
 * This class is responsible for decoding assistant actions. It was extracted from
 * WorkflowAssistantService during the phase-3 refactor. It takes the raw structured
 * reply from the model, normalises each entry, dispatches by type, and ... (10 lines)
 */

// Good
/**
 * Decodes the structured `actions[]` reply into typed actions.
 */
```

```php
// Bad — restating what the code obviously does
// loop over each entry and decode it
foreach ($entries as $entry) { ... }

// Good — no comment
foreach ($entries as $entry) { ... }
```

```php
// Good — a rare, earned comment: the WHY isn't visible in the code
// Smaller models stringify nested objects; decode before treating as a map.
$value = is_string($value) ? json_decode($value, true) : $value;
```

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

## Relationship to the other skills

When a comment is tempted to explain *why a value is shaped oddly*, that's usually a
[`fix-at-the-source`](../fix-at-the-source/SKILL.md) smell — fix the shape instead of documenting the
workaround. A doc should never be the thing keeping a confusing design legible.
