---
name: commandments-writing-detectors
description: "How to write a commandment of your OWN — a project-local rule that judges every file from then on. Read this BEFORE writing or changing a detector in `.commandments/custom/`, when the user asks for a new rule/check/detector, when `commandments make` points you here, or when you are about to reach for a PhpParser node class or a regex to inspect code."
---

# Writing your own commandments

> The shipped rules are not the ceiling. A convention you keep restating in review is a rule you
> haven't written yet. Write it once, and it judges every file from then on.

## The one rule above all: the engine already answers it

**Before you touch a `PhpParser\Node` class, read the method list of `AstNode`.** This is not a
style preference — it is the single most expensive mistake made in this codebase, and it is made
almost every time.

A real first draft of a "no method returns `?Element`" detector ran ~90 lines: it walked
`ClassMethod->returnType` by hand, branched on `NullableType` vs `UnionType`, iterated union
members hunting a `null` identifier, pulled `Node\Name->getAttribute('resolvedName')` with a
`toString()` fallback. Every line correct. Every line redundant. The whole detector is:

```php
return $codebase
    ->whereMethodDeclaration()
    ->where(static fn (AstNode $node): bool => $node->returnsNullableObject())
    ->where(static fn (AstNode $node): bool => is_a((string) TypeName::nullableClass($node->node->returnType), Element::class, true))
    ->get();
```

Same cases caught, `?string` correctly ignored, six lines instead of ninety.

**The tell.** If your detector imports `PhpParser\Node\NullableType`, `UnionType`, or juggles the
`resolvedName` attribute — or if you are writing a private helper that reads, renders, or compares
nodes — stop. You are re-implementing something. So look first:

```bash
# every predicate the node type answers (~150 of them)
grep -o 'public function [a-zA-Z]*' vendor/jessegall/code-commandments/src/Ast/AstNode.php

# the selectors that open a query, and the whole-program graph
grep -o 'public function [a-zA-Z]*' vendor/jessegall/code-commandments/src/Ast/Codebase.php

# the type helpers, and the cross-cutting analyses
grep -o 'public static function [a-zA-Z]*' vendor/jessegall/code-commandments/src/Ast/TypeName.php
ls vendor/jessegall/code-commandments/src/Ast/Support/
```

Grep for the CONCEPT, not the spelling — `nullable`, `return`, `throw`, `loop`, `mutat`, `assign`.

And **never regex code structure.** A member chain, an equality, a nesting depth, a Vue binding —
parse it and query the AST. The frontend has its own parsers for exactly this (`Vue\Expr\Parser`
for the JS inside a binding). A regex over an expression means the engine is missing a tool.

When the predicate genuinely is not there, write it inline for now — and **report it**
(`vendor/bin/commandments feature-request`), because a missing predicate is a gap in the engine,
not a gap in your detector.

## Scaffold it — don't hand-write the plumbing

```bash
vendor/bin/commandments make <Name>                      # a backend (PHP) rule
vendor/bin/commandments make <Name> --engine=frontend    # a Vue one
vendor/bin/commandments make <Name> --skill=absence      # point it at a skill that already exists
```

That writes the three classes into `.commandments/custom/`, registers the detector in
`.commandments/config.php` through the AST, and prints the rest of the process. The folder sits
beside your config, is **not** gitignored, and is **not** PSR-4 mapped — its files are required
directly, so dropping a class in is what makes it loadable. Commit them: they are your rules.

And they are named as yours wherever they fire: `judge` prints `[YourDetector (custom)]` in the
console and in `sins.md`, and says in the section that the fix belongs in `.commandments/custom/`.
So when one of your own rules fires wrongly, tighten it HERE — `commandments report --detector=` is
for the rules the package ships and refuses a project-local one.

## The anatomy — three classes, one job each

**The Skill teaches.** It is what a finding sends the reader to, and the source of truth for what
good looks like. You write the prose (`title`, `trigger`, `intro`, `summary`, `principle`); the
rules, examples and checklist are **projected from its sins** on every `sync`, so they cannot
drift. Its `SKILL.md` is generated into the project's skill library as `commandments-<slug>` — **edit the class,
never the markdown.** Point at a shipped skill instead when your rule belongs to a discipline that
already exists.

**The Sin names.** A `name` (the `--sin=` id), the `skill` it points at **by class** (so a slug
rename can't strand it), a `description` (the symptom, one line) and a `rule` (the positive
directive — "Return `Element::none()`, never null", not "you returned null").

**The Detector finds.** Two methods and no more: `sin()` returns the sin, `find()` returns the
locations. **No fix logic, no severity, no rubric** — the skill teaches, the detector only finds.

```php
final class NullableElementReturnDetector implements Detector
{
    public function sin(): Sin
    {
        return new NullableElementReturn;
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereMethodDeclaration()                                 // a selector opens the query
            ->where(static fn (AstNode $n): bool => $n->returnsNullableObject())   // one check
            ->reject(static fn (AstNode $n): bool => $n->isInEnum())               // per line
            ->get();
    }
}
```

A selector opens a `Query`; `where`/`reject` narrow it, **one check per line** so it reads like a
sentence; a terminal (`get`, `locations`, `count`, `first`) returns matches that know their
`file:line`. Put the cheap checks first — it reads better and runs better. A frontend detector is
the SAME shape over `Vue\Codebase` → `ElementMatch`; if yours doesn't look like a backend one,
you're fighting the engine.

## Classify by what the code IS, never by what it is CALLED

The cardinal rule. Derive the answer from the AST or the resolved type — `extends`/`implements`,
attributes, constructor shape, the type a value actually resolves to. **Never** from a class,
method or variable name, a suffix, or a hardcoded list. A name check is a smell you must justify:
names lie, and a rule built on one fires on the wrong code the first time somebody renames well.

## Prove it fires — a detector nobody has watched is a guess

A detector that compiles is not a detector that works. **Write a probe**: a throwaway file under a
scanned path holding one example of every form you mean to catch **plus the near-misses you must
NOT catch**.

```php
// src/ProbeNullableElementReturn.php  — delete after
public function header(): ?Button { }        // must fire
public function footer(): Button|null { }    // must fire — the union form
public function body(): Button { }           // must NOT fire — honest return
public function title(): ?string { }         // must NOT fire — nullable, but not an Element
```

```bash
vendor/bin/commandments judge src --sin=<your-sin> --no-checklist
```

Confirm **exactly** the intended lines are flagged — not "some lines". Then delete the probe. The
near-misses are the whole point: a rule that fires on everything is not a rule.

## Calibrate on real code — before you trust it

A green probe proves it *can* fire; it does not prove it is *right*. Run it over your actual
source and **read the hits by eye**, judging each **against the skill** — never against what the
code happens to do today.

- **Volume proves nothing.** 400 hits can be 400 real sins. A widespread pattern is not
  "convention" that excuses a finding. Do not soften a rule because it fires a lot.
- **Only a genuine false positive invalidates it** — code that is *correct under your architecture*
  yet gets flagged. Then tighten with a **principled `reject`** (an AST/semantic condition), never
  a name list.
- **Some ideas die here.** If no AST signal separates the sin from a legitimately valid look-alike
  — if the difference is only the author's intent — the detector is not viable. Cut it. That is a
  successful outcome, not a failure.

## Should it auto-fix?

Only when the fix is **mechanical and unambiguous** — one right answer the rewrite can always
produce (drop a redundant return type, hoist a stray member, reshape a docblock). Then implement
`Repentable`, name a scribe, and write it: **[`reference/scribes.md`](reference/scribes.md)** has
the whole contract — what a `RepentScribe` returns, the `Draft`/`Writer`/`Span` layers that do all
the editing (never a regex, never line surgery), how the `ScribeChain` runs it, a worked example,
and how to prove one with `repent --dry-run`.

Most rules should not. If the honest fix depends on what the code MEANS — which value object to
introduce, where absence really belongs, what to name the thing — an auto-fix would launder the
problem instead of solving it. Leave it to the reader, and let the skill teach them. A wrong
auto-fix is worse than no auto-fix.

## The cadence, in order

1. **Load this skill** and skim the arsenal — before writing a line.
2. `commandments make <Name>` — scaffold the three classes.
3. **Write the teaching first.** If you can't state what good looks like, the detector doesn't know
   what it's looking for either.
4. Name the sin: the symptom, and the rule as a positive directive.
5. Write the `where()` chain — AST/semantic, one check per line.
6. **Probe it.** Every form you mean to catch, plus the near-misses.
7. **Calibrate** on real code. Tighten, or cut.
8. **Auto-fix it?** Only if the fix is mechanical — then write the scribe
   ([`reference/scribes.md`](reference/scribes.md)) and preview it with `repent --dry-run`.
9. `vendor/bin/commandments sync` — publishes your skill so the agent can load what the finding
   points at.

## Reference

<!-- BEGIN: commands:make (auto-generated, run `composer sins`) -->
| Command | Does |
|---|---|
| `commandments make <Name>` | scaffold a backend (PHP) commandment and register it |
| `commandments make <Name> --engine=frontend` | scaffold a frontend (Vue) one instead |
| `commandments make <Name> --skill=NAME` | point the sin at an EXISTING skill (shipped or your own) instead of writing a new one |

<!-- END: commands:make -->
