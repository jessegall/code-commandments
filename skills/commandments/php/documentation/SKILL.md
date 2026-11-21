---
name: commandments-php-documentation
description: "How to document — and mostly NOT. Docblocks are 1–2 lines (3 max), present-tense, about the code as it is NOW; inline comments are RARE and only ever explain a non-obvious *why*; NEVER narrate the past or a change (\"previously…\", \"used to…\", \"now we…\", \"refactored to…\"). Read this the MOMENT you are about to write a docblock (`/**`), an inline comment (`//`), or a class/method description."
---

# Documentation — concise, present-tense, rare

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> A docblock describes the **code as it is**, in as few words as possible — write one. An inline comment
> is a last resort. Neither is a changelog, a tutorial, or a story about the refactor. Most code needs no
> inline comment at all.

## The principle

A docblock and a comment are not free: every line a reader must scan costs them time, and a line that just
restates the code, or narrates how it got here, wastes that time for nothing. The bar is high —
write a doc only when it tells the reader something the code itself does not.

Docs are still **wanted**, not banned. A short docblock on a class or method is good and expected: one
sentence saying what it *is* or *does*, plus the `@param` / `@return` / `@throws` type contract. Keep it to
a line or two, present-tense, about the code as it is now — never *how* it works internally, *why* it
changed, or what it *used to* be. Git holds the history; when you replace code, just replace it — don't leave
a comment explaining what it used to be.

Inline comments are the rarest of all — default to none. The code already says *what* it does; the only
comment worth writing explains a non-obvious **why** the code can't: a hidden invariant, a workaround for an
external bug, a constraint the reader can't infer. (A structural section divider in a large class is fine
if the codebase already uses them — structural, not narrative.) Everything else: don't write it.

## Rules

- [ ] Write a docblock as a block: the opening delimiter on its own line, one star per line of content, the closing delimiter on its own line.
      _expand it to a multi-line docblock_

## Worked example

### inline-docblock

A docblock whose delimiter shares a line with its text — a one-liner, or a block that opens or closes next to content

```php
----------[ Bad ]----------

final class LogLine
{
    public string $level = 'info';

    private int $depth = 0;

    /**
     * @var list<LogLine>
     */
    public array $children = [];

    /**
     * A bool about the line itself, named as a claim instead of a question.
     */
    public function reports(): bool
    {
        return $this->level === 'error';
    }

    /**
     * The FIX is the NAME: same body, same class, asked as a question. `if ($line->isErrored())`
     * reads as English at the call site, where `if ($line->reports())` reads as a claim.
     */
    public function isErrored(): bool
    {
        return $this->level === 'error';
    }
}

----------[ Good ]----------

final class LogEntry
{
    public string $level = 'info';

    /**
     * @var list<LogEntry>
     */
    public array $children = [];

    public function isError(): bool
    {
        return $this->level === 'error';
    }
}
```

## Commands

- `{{binary}} judge --skill=php/documentation` — find every one of these in the codebase.
- `{{binary}} info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `inline-docblock`.
- `{{binary}} report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`php/fix-at-the-source`](../fix-at-the-source/SKILL.md) — fix the shape instead of documenting the workaround. A doc should never be the thing keeping a confusing design legible.
