<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\Backend;

use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class BehaviourPerMethod extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'backend/behaviour-per-method',
            tier: Tier::KeepInMind,
            order: 18,
        );
    }

    public function title(): string
    {
        return "One method, one behaviour — never a flag that picks";
    }

    public function trigger(): string
    {
        return "A parameter that selects WHICH behaviour runs rather than feeding one. When a method's whole body is `if (\$flag) { … } else { … }`, it is two methods sharing a name, and every call site reads `render(\$order, true)` — a truth value that says nothing about what it asked for. Split it into two named methods and let the caller say which it wants. Read this before adding a `bool` parameter, before writing a method whose body is one branch on a parameter, and when a call site passes a bare `true`/`false` literal.";
    }

    public function intro(): string
    {
        return "`render(\$order, true)` — true *what*? The caller already knows which of the two
things it wants; the flag is that decision, flattened into a truth value and
handed over for the callee to unpack again.";
    }

    public function summary(): string
    {
        return "a parameter that picks WHICH behaviour runs means two methods share one name — split them and let the call site say which it wants, instead of passing a bare `true`.";
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
A parameter is supposed to be something a method **works with**. A flag argument is
something the method works *around*: it arrives, gets tested once, and decides which
half of the body runs. The two halves were never one method — they share a name and a
signature, and nothing else.

The cost lands at the call sites, where it is worst:

- **`send($order, true)` is unreadable.** Nothing at the call site says what `true`
  means. Every reader has to open the callee to find out, every time.
- **The two behaviours cannot evolve apart.** A parameter one half needs becomes a
  parameter the other half must ignore, and the signature grows to fit the union of
  two jobs it was never doing together.
- **It hides how the code is really used.** Split, you can see at a glance that
  `sendDraft()` has three callers and `sendFinal()` has thirty. Fused, both are just
  "send".
- **Flags multiply.** Two bools mean four documented behaviours in one body, and
  usually only three of them were ever intended.

So: **name the two behaviours.** `render($order, true)` becomes `renderCompact($order)`
and `renderFull($order)`. The shared middle, if there is any, becomes a private method
they both call — which is the honest structure, and was invisible before.

### What is NOT this sin

- **A flag that is DATA the method stores or forwards.** `setVisible(bool $visible)`,
  `withTrailingSlash(bool $on)` — the value is the point, not a switch between two
  behaviours. It is kept, not obeyed.
- **A flag that tunes ONE behaviour.** `parse($input, strict: true)` where strictness
  changes what counts as an error inside one parsing pass, rather than selecting a
  different pass, is one method doing one job more or less leniently.
- **An early return that guards.** `if ($force) { return $this->overwrite(); }` at the
  top of a method is a guard clause and belongs there — see guard-clauses-and-flow.
  The smell is the body being *nothing but* a two-way branch.
- **An enum or object that names the choice.** `render($order, Density::Compact)` is
  already honest: the argument says what it means at the call site, and adding a third
  case does not add a fourth code path by accident.

### The tell

The method's entire body is `if ($flag) { … } else { … }`, or a `match ($flag)` with a
`true` arm and a `false` arm. Ask what you would call each half on its own. If both
halves have an obvious name, they are already two methods — give them their names.
PRINCIPLE;
    }

    public function related(): array
    {
        return [
            EnumsWithBehaviour::class => "when the choice has more than two cases, it is an enum — and the behaviour belongs on it.",
            GuardClausesAndFlow::class => "an early return that guards is the shape this one is NOT; check preconditions at the top and leave.",
            PassTheObject::class => "the sibling on the other side: a caller that pre-decides with a bool it computed from an object it holds.",
        ];
    }
}
