---
name: detector-fixtures
description: The self-checking fixtures that prove every rule — the sin markers ARE the test spec, fixture.Fixture.Prove checks missed/unexpected/righteous/fixed in every language a rule judges, and every rule must fire on ≥3 DIVERSE scenarios (different files, <60% class overlap, max-clique) plus have a righteous twin it must NOT flag. Read this when adding/adjusting a rule's fixtures or debugging a fixture test failure.
---

# The self-checking fixture — markers ARE the spec

Each language has one fixture project under `tests/Fixtures/` (`backend` for PHP, `frontend` for Vue and
TypeScript, `python`, `csharp`, `go`): a coherent "Shop" app that is **never run**, only parsed. A marker
declares "the rule for this sin MUST flag here": `#[Sinful(Name::class)]` on a PHP class or method, a
`# @sin Name` or `// @sin Name` comment above the code in Python, C#, Go and TypeScript, `<!-- @sin Name -->`
in a Vue template. `Name` is the sin's name or any of its aliases. Absence of a marker means the spot is
righteous. **Adding a test = adding a marker.**

```php
#[Sinful(EnumValueMatch::class)]   // name the SIN class; method- or class-scope; repeatable
public function badge(Order $order): string
{
    return match ($order->status->value) { /* … */ };   // the sin
}
```

## What the harness enforces (`fixture.Fixture.Prove`)

`fixture/rules_test.go` runs every rule over every fixture project of a language it judges
(`commandments rule prove --all` does the same from the command line) and, per rule, reports:
- **missed** — a sin marker it did NOT flag (a hole),
- **unexpected** — code it flagged that is NOT marked (a false positive, OR an unmarked sin you forgot to
  mark),
- a flagged **righteous twin**, and a flagged **fix** (the repair would still be the sin).

All must be empty. So the whole *unmarked* fixture is the false-positive guard. The backend fixture is read
from its committed stream, so a change to it needs `scripts/dev go run ./engine/php/shop/generate` before
`scripts/dev go test ./fixture/`.

## The ≥3-diverse-scenarios floor

Every rule must fire, in each language it judges, on at least **three mutually-DIVERSE** findings:
different files **and** <60% class-source overlap (a **max-clique** over the
diversity graph — order-independent, so a look-alike "hub" finding can't mask a
real trio, and copy-paste/renames don't count). The 60% threshold is calibrated
from measured data (genuinely-different scenarios cluster 38–59%; renamed copies
66–100%).

**Making three the same sin look different:** vary the genuine *shape*, not the
names. Renaming `$prices`→`$rates` scores ~80%; a structurally different method
scores ~40%. Three forms of one sin: a string state-machine vs int counters vs a
keyed map; a match-return vs match-assign vs `switch`; an HTTP client vs a file
reader. For whole-class sins (DTOs), give each class genuinely different
behaviour methods so the shared shell is a small fraction.

## Righteous twins — non-negotiable

Every rule needs a deliberate **look-alike that it must NOT flag**, so the
"no unexpected" path is meaningfully exercised: e.g. `OrderData::from()` next to
`new OrderData()`, a `findOrFail()` next to a de-nulled `?T` finder, `::collect()`
next to `::from()`-in-a-loop. A file can be righteous for one rule and sinful
for another.

## `#[Fixed]` — the repair, and not the same thing as righteous

`#[Righteous]` is a claim about the **rule**: a look-alike it must not flag,
which in practice is usually a documented *exemption*, code that legitimately
dodges the rule. `#[Fixed]` is a claim about the **fix**: the `#[Sinful]` code
repaired the way that sin's rule says to repair it.

```php
#[Sinful(NullableCallback::class)]
public function run(Closure $work, ?Closure $onRetry = null): mixed   // null-normalised in the body

#[Fixed(NullableCallback::class)]
public function runWith(Closure $work, Invokable $onRetry = new NoOp): mixed   // the rule's own fix
```

A repair must go unflagged, so `#[Fixed]` implies `#[Righteous]`, never the
reverse: the harness fails a rule that flags its own fix. Frontend twin:
`<!-- @fixed Name -->` above a template element, `// @fixed Name` above a module
declaration.

A repair is rarely one declaration: `$customer->suspend($reason)` is only the
call site that got thinner, and the fix is `Customer::suspend()`. Mark every
declaration that carries the repair, and never let one name something the
fixture does not declare.

## A fixture proves a rule; it teaches nothing

No published skill takes an example from a fixture. A skill is one lesson
written by hand, `skill/<package>/<subject>.md`, embedded as the `Body` of its
definition: an opening, `## The principle`, then one `### <sin-name>` section
for each sin it fixes, each with a short paragraph and one fenced example that
shows the bad code, the good code and an arrow comment (`// ← why`) at the line
the lesson is about. A subject every language shares is written once, in
pseudocode, under `skill/shared/`; a sin only one language has gets a section
headed `### <sin-name> — in PHP` in that language's own code.

So a new rule needs two things beside its JSON file: its markers in the
fixtures, and its section in the lesson of the skill it points at.
`TestEverySkillIsALessonWithAnExampleForEverySinItFixes` (`skill/render`)
fails until the section is there, and `commandments info <sin>` prints that
section's example.

## Diagnostics

`commandments rule try rules/<folder>/<name>.json tests/Fixtures/<project>` prints every match of one rule
with its line; `bin/commandments judge tests/Fixtures/backend --sin=X --no-checklist` shows them as findings.
`scripts/dev go test -run 'TestEveryRuleFlagsExactlyWhatTheFixtureMarks/backend' ./fixture/` proves one
project.

## Related

- [[writing-detectors]] · [[detector-engine]]
