---
name: commandments-backend-behaviour-per-method
description: "A parameter that selects WHICH behaviour runs rather than feeding one. When a method's whole body is `if ($flag) { … } else { … }`, it is two methods sharing a name, and every call site reads `render($order, true)` — a truth value that says nothing about what it asked for. Split it into two named methods and let the caller say which it wants. Read this before adding a `bool` parameter, before writing a method whose body is one branch on a parameter, and when a call site passes a bare `true`/`false` literal."
---

# One method, one behaviour — never a flag that picks

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> `render($order, true)` — true *what*? The caller already knows which of the two
> things it wants; the flag is that decision, flattened into a truth value and
> handed over for the callee to unpack again.

## The principle

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

## Rules

- Split a method whose body is one branch on a flag into two NAMED methods — never make a call site say `true`.
  _name each half for what it does (`renderCompact()` / `renderFull()`), with any shared middle as a private method both call_

## Bad → good

### flag-argument

a method whose whole body branches on a `bool` parameter — two methods sharing one name

```php
----------[ Bad ]----------

// Two announcements sharing a name. At the call site this reads `announce($msg, true)` — true
// what? Nobody can tell without opening this method.

public function announce(string $message, bool $urgent): void
{
    if ($urgent) {
        $this->log->record('SIREN ' . strtoupper($message));
    } else {
        $this->log->record($message);
    }
}

----------[ Good ]----------

// The same two announcements, named. The call site now says which one it wanted, and each half
// is free to grow its own parameters without the other having to ignore them.

public function announceUrgently(string $message): void
{
    $this->log->record('SIREN ' . strtoupper($message));
}
```

## When it fires

- a method whose whole body branches on a `bool` parameter — two methods sharing one name — `FlagArgumentDetector`

## Checklist

- [ ] Split a method whose body is one branch on a flag into two NAMED methods — never make a call site say `true`.

## Related skills

- [`backend/enums-with-behaviour`](../enums-with-behaviour/SKILL.md) — when the choice has more than two cases, it is an enum — and the behaviour belongs on it.
- [`backend/guard-clauses-and-flow`](../guard-clauses-and-flow/SKILL.md) — an early return that guards is the shape this one is NOT; check preconditions at the top and leave.
- [`backend/pass-the-object`](../pass-the-object/SKILL.md) — the sibling on the other side: a caller that pre-decides with a bool it computed from an object it holds.
