<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\Python;

use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class Absence extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'python/absence',
            tier: Tier::Mandatory,
            order: 31,
        );
    }

    public function title(): string
    {
        return 'Python absence — decide "missing" where the value is born';
    }

    public function trigger(): string
    {
        return "Modelling a value that might not be there in Python — a return typed `X | None` or `Optional[X]`, a `return None` for \"not found\", an `if x is None:` or `x or default` at a call site, a `.get(key, default)`, or deciding between raising, returning an empty collection and returning `None`. Read this BEFORE writing any of them.";
    }

    public function intro(): string
    {
        return "`None` is not a way to model absence. It is the *absence of a decision* about absence.
Make the decision once, where the value is born, so no reader downstream has to guess what
\"not there\" means.";
    }

    public function summary(): string
    {
        return 'decide absence where the value is born — raise, return an empty collection, or a Null Object — instead of an `X | None` every caller re-checks; never `or ""` a required value.';
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
### Ask in order, stop at the first yes

1. **Can it actually be missing, or is "missing" a broken state?** A config the program cannot
   run without, a record a caller just created, a key the code itself wrote — absence there is a
   failure. **Raise a named exception.** Do not return `None` for it.
2. **Does "nothing" have a natural empty form?** A search with no hits is `[]`; a mapping with no
   entries is `{}`; a behaviour with nothing to do is a Null Object — an instance whose methods do
   nothing. Return that, and every caller loops or calls with no special case.
3. **Is it a genuine "look for it; it may miss" that more than one caller handles?** Then
   `X | None` is honest — but the check belongs at each caller *because* each one decides
   something different. If every caller writes the same `if result is None: raise …` or `or
   default`, the decision was the producer's: move it there.

### `or default` answers the wrong question

`name = user.name or ""` fills a required slot with a value nobody chose, and loses the question:
was the name missing, or genuinely empty? `x or []` also swallows `0`, `False` and `""` — every
falsy value, not just `None`. If the value can be missing, handle that case; if it cannot, do not
defend against it.

### The one honest `None`

A single local lookup checked right where it is produced — one caller, one `is None`, done — needs
no ceremony. The smell is a `None` that **travels**: returned, passed on, and re-checked at every
place it lands.
PRINCIPLE;
    }

    public function related(): array
    {
        return [
            \JesseGall\CodeCommandments\Skills\Backend\Absence::class => 'the same decision on the PHP backend, with `Option`.',
            \JesseGall\CodeCommandments\Skills\Python\Exceptions::class => 'when "missing" is a broken state, the named exception to raise.',
        ];
    }

    public function languages(): array
    {
        return [Language::Python];
    }
}
