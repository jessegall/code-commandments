<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\CSharp;

use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class BehaviourPerMethod extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'csharp/behaviour-per-method',
            tier: Tier::KeepInMind,
            order: 39,
        );
    }

    public function title(): string
    {
        return 'C# behaviour per method — one method, one job';
    }

    public function trigger(): string
    {
        return "A C# parameter that picks which behaviour runs instead of feeding one. When a method's whole body is `if (flag) { … } else { … }`, it is two methods sharing a name, and every call reads `Render(order, true)`. Read this before adding a `bool` parameter, before making a required parameter nullable so that leaving it out means 'all of them', and when a call passes a bare `true` or `false`.";
    }

    public function intro(): string
    {
        return "`Render(order, true)` — true *what*? The caller already knows which of the two things it wants; the
flag is that decision, squeezed into a `bool` and handed over for the method to unpack again.";
    }

    public function summary(): string
    {
        return 'a parameter that picks WHICH behaviour runs means two methods share one name — split them and let the call say which it wants, instead of passing a bare `true`.';
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
A parameter is something a method **works with**. A flag argument is something it works *around*: it
arrives, is tested once, and decides which half of the body runs. The two halves were never one method —
they share a name and a signature, and nothing else.

- **`Send(order, true)` is unreadable.** Nothing at the call says what `true` means.
- **The halves cannot change separately.** A parameter one half needs becomes one the other must ignore.
- **It hides how the code is used.** Split, `SendDraft` has three callers and `SendFinal` thirty.
- **Flags multiply.** Two `bool`s are four behaviours in one body, and usually three were meant.

So **name the two behaviours**: `Render(order, true)` becomes `RenderCompact(order)` and
`RenderFull(order)`, and whatever they share becomes a private method both call.

### A named argument does not split it

`Render(order, compact: true)` is better than a bare `true` — but the body still runs one of two jobs behind
one name. A named argument is how to pass a flag that *tunes* a behaviour; a flag that *selects* one is still
two methods.

### The disguise: `null` that means "all of them"

A required parameter made nullable so that leaving it out asks a different question — `Fields(string kind)`
becomes `Fields(string? kind = null)`, and `Fields()` now means "every one". That is two questions behind one
name, chosen by what the call leaves out, which says even less than a bare `true`. The fix is the same:
`FieldsOfKind(kind)` and `Fields()`.

### What is NOT this sin

- **A flag that is data the method stores or passes on** — `SetVisible(visible)`: the value is the point.
- **A flag that tunes one behaviour** — `Parse(text, strict: true)`, where strictness changes what counts as
  an error inside one pass, not which pass runs.
- **A guard at the top** — `if (force) return Overwrite();` is a guard clause (see `csharp/flow`). The smell
  is a body that is *nothing but* the two-way branch.
- **An enum that names the choice** — `Render(order, Density.Compact)` already says what it means.

### The tell

The whole body is `if (flag) { … } else { … }`, a `flag ? A() : B()`, or `if (kind is null) … else …` on a
parameter that used to be required. Ask what you would call each half on its own. If both have an obvious
name, they are already two methods — give them their names.
PRINCIPLE;
    }

    public function related(): array
    {
        return [
            \JesseGall\CodeCommandments\Skills\Backend\BehaviourPerMethod::class => 'the same discipline in PHP.',
            Enums::class => 'when the choice has more than two cases, it is an `enum` — and the behaviour belongs beside it.',
            Flow::class => 'an early return that guards is the shape this one is NOT.',
        ];
    }

    public function languages(): array
    {
        return [Language::CSharp];
    }
}
