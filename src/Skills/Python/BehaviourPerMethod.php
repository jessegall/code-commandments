<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\Python;

use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class BehaviourPerMethod extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'python/behaviour-per-method',
            tier: Tier::KeepInMind,
            order: 38,
        );
    }

    public function title(): string
    {
        return 'Python behaviour per method — never a flag that picks';
    }

    public function trigger(): string
    {
        return "A Python parameter that selects WHICH behaviour runs rather than feeding one. When a function's whole body is `if flag: … else: …`, it is two functions sharing a name, and every call reads `render(order, True)`. Read this before adding a `bool` parameter, before widening a required parameter to `X | None = None` so that leaving it out means 'all of them', and when a call passes a bare `True`/`False`.";
    }

    public function intro(): string
    {
        return "`render(order, True)` — True *what*? The caller already knows which of the two things it wants;
the flag is that decision, flattened into a truth value and handed over for the callee to unpack again.";
    }

    public function summary(): string
    {
        return 'a parameter that picks WHICH behaviour runs means two functions share one name — split them and let the call say which it wants, instead of passing a bare `True`.';
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
A parameter is something a function **works with**. A flag argument is something it works *around*: it
arrives, is tested once, and decides which half of the body runs. The two halves were never one function —
they share a name and a signature, and nothing else.

- **`send(order, True)` is unreadable.** Nothing at the call says what `True` means.
- **The halves cannot evolve apart.** A parameter one half needs becomes one the other must ignore.
- **It hides how the code is used.** Split, `send_draft` has three callers and `send_final` thirty.
- **Flags multiply.** Two bools are four behaviours in one body, and usually three were meant.

So **name the two behaviours**: `render(order, True)` becomes `render_compact(order)` and
`render_full(order)`, and whatever they share becomes a private function both call.

### A keyword does not split it

`render(order, *, compact: bool = False)` makes the call say `compact=True`, and that is better than a bare
`True` — but the body still runs one of two jobs behind one name. Keyword-only is how to pass a flag that
TUNES a behaviour; a flag that SELECTS one is still two functions.

### The disguise: `None` that means "all of them"

A required parameter widened so that leaving it out asks a different question — `fields(kind: str)` becomes
`fields(kind: str | None = None)`, and `fields()` now means "every one". That is two questions behind one
name, selected by an ABSENCE at the call, which says even less than a bare `True`. The fix is the same:
`fields_of_kind(kind)` and `fields()`.

### What is NOT this sin

- **A flag that is DATA the function stores or forwards** — `set_visible(visible)`: the value is the point.
- **A flag that tunes ONE behaviour** — `parse(text, strict=True)`, where strictness changes what counts as
  an error inside one pass rather than choosing another pass.
- **A guard at the top** — `if force: return self._overwrite()` is a guard clause (see `python/flow`). The
  smell is a body that is *nothing but* the two-way branch.
- **An enum that names the choice** — `render(order, Density.COMPACT)` already says what it means.

### The tell

The whole body is `if flag: … else: …`, a `match flag:` with a `True` and a `False` case, or
`if kind is None: … else: …` on a parameter that used to be required. Ask what you would call each half on
its own. If both have an obvious name, they are already two functions — give them their names.
PRINCIPLE;
    }

    public function related(): array
    {
        return [
            \JesseGall\CodeCommandments\Skills\Backend\BehaviourPerMethod::class => 'the same discipline over PHP methods.',
            Enums::class => 'when the choice has more than two cases, it is an `Enum` — and the behaviour belongs on it.',
            Flow::class => 'an early return that guards is the shape this one is NOT.',
        ];
    }

    public function languages(): array
    {
        return [Language::Python];
    }
}
