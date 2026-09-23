---
name: commandments-python-dependency-direction
description: "Adding an import between two of the project's own Python packages. Read this before `from shop.ui.shared import …` inside `shop.ui.elements`, before an import inside a function added to dodge a circular import, and when a namespace-cycle or namespace-dependency finding points here. A declared layer may only import the layers it said it may use."
---

# Python dependency direction — imports point down the stack

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> A package is a claim about what depends on what. One import going the wrong way and two packages are a single
> tangled unit wearing two names, so the direction is declared once and every import is judged against it.

## The principle

`shop.ui.elements` says "these are the primitives"; `shop.ui.shared` says "these are built FROM the
primitives". The claim is worth exactly as much as its imports: one `from shop.ui.shared import Panel` inside
`elements` and the two cannot be understood, tested or reused apart.

Nothing about the arrow shows when you write it. The import runs and the tests pass. Python even hands you
the escape hatch — move the import into the function body and the circular-import error goes away — and
the cycle is still there, only hidden.

### Declare the stack

The direction is declared once, in the project's `.commandments/config.php`, each layer naming the layers
it may use:

```php
$config->configure(fn (\JesseGall\CodeCommandments\Detectors\Python\NamespaceDependencyDetector $d) => $d
    ->layer('shop.ui.elements')                                  // primitives: itself only
    ->layer('shop.ui.shared', mayUse: ['shop.ui.elements'])      // built from the primitives
    ->layer('shop.domain')                                       // knows nothing about the UI
);
```

### What is judged

- **Every import**: `import x`, `from x import y`, a relative `from ..x import y`, and one written inside a
  function or behind `if TYPE_CHECKING:`. Where it sits does not change what it depends on.
- **Only declared packages.** The standard library, a third-party package, or a package the project never
  declared is always allowed.
- **Only from a declared layer.** A layer contains its own sub-packages, so imports within a layer are fine.
- **Cycles, declared or not.** Two of the project's packages importing each other are one package, whatever
  the folders say.

### First: which side is wrong?

A layer finding measures the code against a declaration someone wrote, and a declaration can be stale. Before
changing either, ask whether the declared stack still describes the design this project wants. Usually the
import is the accident; when you conclude the declaration is wrong, say so to the user with your reasoning.
Never quietly edit the declaration to make a finding go away.

### The fix

Move the thing both sides need down into the lower layer, pass it in from above, or invert the dependency
behind a protocol the lower layer owns. An import moved into a function body is not a fix: the arrow is
still there.

## Rules

- [ ] Keep imports between the project's packages pointing one way; two packages that import each other are a cycle.
      _Move what both need into the lower package, pass it in from above, or invert it behind a protocol the lower one owns._

## Worked example

### python-namespace-cycle

two of the project's packages import each other — a cycle that makes them one package wearing two names

```py
----------[ Bad ]----------

def results_page(query: str) -> str:
    from shop.search import engine
    return engine.render(query)

----------[ Good ]----------

from ..tracking.events import record
```

## Commands

- `vendor/bin/commandments judge --skill=python/dependency-direction` — find every one of these in the codebase.
- `vendor/bin/commandments info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `python-namespace-cycle`.
- `vendor/bin/commandments report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`backend/dependency-direction`](../../backend/dependency-direction/SKILL.md) — the same discipline over PHP namespaces.
- [`python/fix-at-the-source`](../fix-at-the-source/SKILL.md) — the arrow that points the wrong way is where the fix belongs.
