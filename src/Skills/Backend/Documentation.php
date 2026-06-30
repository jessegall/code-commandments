<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\Backend;

use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class Documentation extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'backend/documentation',
            title: "Documentation — concise, present-tense, rare",
            description: "How to document — and mostly NOT. Docblocks are 1–2 lines (3 max), present-tense, about the code as it is NOW; inline comments are RARE and only ever explain a non-obvious *why*; NEVER narrate the past or a change (\"previously…\", \"used to…\", \"now we…\", \"refactored to…\"). Read this the MOMENT you are about to write a docblock (`/**`), an inline comment (`//`), or a class/method description.",
            tagline: "A docblock describes the **code as it is**, in as few words as possible — write one. An inline comment
is a last resort. Neither is a changelog, a tutorial, or a story about the refactor. Most code needs no
inline comment at all.",
            summary: "concise, present-tense docs; rare inline comments; never narrate the past.",
            tier: Tier::Mandatory,
            order: 6,
        );
    }

    public function body(): string
    {
        return <<<'BODY'
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
BODY;
    }


    public function related(): array
    {
        return [
            FixAtTheSource::class => "fix the shape instead of documenting the workaround. A doc should never be the thing keeping a confusing design legible.",
        ];
    }
}
