---
name: commandments-backend-documentation
description: "How to document — and mostly NOT. Docblocks are 1–2 lines (3 max), present-tense, about the code as it is NOW; inline comments are RARE and only ever explain a non-obvious *why*; NEVER narrate the past or a change (\"previously…\", \"used to…\", \"now we…\", \"refactored to…\"). Read this the MOMENT you are about to write a docblock (`/**`), an inline comment (`//`), or a class/method description."
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

- [ ] Comment what the code IS now, never its history — no "formerly/used to be/refactored/no longer an X" archaeology; git holds the past.
- [ ] Keep a class docblock to one tight paragraph — a multi-paragraph essay means the class does too much.
- [ ] A docblock must add meaning beyond the signature — drop `@param Type $x` lines that only restate an already-typed parameter.
- [ ] A `{@see}`/`{@link}` must resolve to a real class. A cross-reference to a first-party class the codebase no longer declares is stale documentation — repoint it at the current class or delete it. (References into another vendor namespace are left alone; they can't be verified here.)
- [ ] Write a docblock as a block: the opening delimiter on its own line, one star per line of content, the closing delimiter on its own line.
      _expand it — `repent` does this for you_
- [ ] State what the code IS, affirmatively — a comment that defends it against a strawman (that it is "not random", "no magic", "not a typo") is negative space; make the code self-evident and delete the comment.
- [ ] An inline comment must say something the code does not — never narrate the statement below it; if every word of the comment is already a word of the code, delete the comment.
- [ ] One declaration carries ONE docblock — merge a stack into a single block, because the language hands only the last one to a reader's tooling.
      _merge them into one block — `repent` does this for you_

## Worked example

### archaeology-comment

History/archaeology comments ("formerly / used to be / refactored / no longer an X / was extracted")

```php
----------[ Bad ]----------

/**
 * @param  array<string, mixed>  $event
 */
public function handle(array $event): void
{
    // formerly lived inline in the StripeController; was extracted here
    $type = $event['type'];

    $this->record($type, $event['id'] ?? throw new \InvalidArgumentException('event id is required'));
}

----------[ Good ]----------

/**
 * @param  array<string, mixed>  $event
 */
public function handleRefund(array $event): void
{
    // a refund carries no id of its own, so the charge reference identifies the row
    $this->record('refund', $this->reference($event));
}
```

The other 7 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `vendor/bin/commandments judge --skill=backend/documentation` — find every one of these in the codebase.
- `vendor/bin/commandments info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `archaeology-comment`, `bloated-docblock`, `ceremony-docblock`, `dangling-doc-reference`, `inline-docblock`, `negative-space-comment`, `restated-comment`, `stacked-docblock`.
- `vendor/bin/commandments repent --sin=<sin>` — auto-fix, for `inline-docblock`, `stacked-docblock`. Review it with `--dry-run` first.
- `vendor/bin/commandments report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 8 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`backend/fix-at-the-source`](../fix-at-the-source/SKILL.md) — fix the shape instead of documenting the workaround. A doc should never be the thing keeping a confusing design legible.
