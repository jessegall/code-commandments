---
name: writing-detectors
description: How to author a rule end-to-end in version 8 — one JSON file under rules/ that names its sin and finds it over the generic tree, proven by the fixture markers of every language it judges, calibrated on real code. Read this BEFORE adding or changing a rule.
---

# Writing a rule

A rule is **one JSON file** under `rules/<engine>-<subject>/<name>.json`. It names its sin, points at the skill
that teaches the fix, and says how to find it over the generic tree. No fix logic, no severity: the skill
teaches, the rule finds. There is no Go detector, no sin type and no language package to add.

```json
{
  "$schema": "commandments:neutral-rule/v2",
  "engine": ["csharp", "go", "php", "python", "typescript"],
  "sin": {
    "name": "redundant-else",
    "aliases": ["RedundantElseDetector"],
    "skill": "shared/flow",
    "description": "An `else` after an `if` branch that already left",
    "rule": "Drop the `else` after a branch that returns, throws, continues or breaks.",
    "suggestion": "Delete the `else` and dedent what was inside it."
  },
  "find": {
    "select": "if",
    "as": "b",
    "where": ["and", ["exists", "$b.else"], ["not", ["completes", "$b.then"]]],
    "emit": "$b"
  }
}
```

`rule/library.go` loads every file under `rules/` (embedded in the binary), its on-disk override and a project's
`.commandments/custom/`, through one loader; `rule/evaluate.go` runs the `find`. A file enrols itself: there is
no list to add it to. `aliases` keep every former name a config, a report or a marker may still use.

## The rules

1. **Read the tree, never names.** A `find` selects neutral kinds (`if`, `loop`, `call`, `catch`, …) and reads
   their named roles (`$b.then`, `$b.else`, `$c.callee`) and facts (types, resolved symbols, modules) as
   `contract/CONTRACT.md` defines them. Never match a class, method or variable name, a suffix or a list.
2. **One rule, every language.** A rule is a concept, written once over the generic tree. It judges every
   engine in its `engine` list through the same `find`. A fact the rule needs that the tree lacks is added
   to the bridge that parses the language and to the contract, never decided in the rule per language.
3. **One check per line of `where`.** An `and` of small, named checks reads like a sentence; repeated logic
   goes into `definitions` and is called by name.
4. **Best-of-the-best only.** A rule catches a real, principled architectural sin with no false positives.
   Skip raw counts, role-by-name guesses and anything needing natural-language understanding.

## The cadence

1. **See the tree.** `commandments rule explain <file> --line=N` prints every node the rule will read: its
   kind, role, name, text and what it resolves to. `commandments rule schema` prints every operator.
2. **Write the file**, with `"unpublished": true` in its `sin` block while it is calibrated: every catalogue
   skips it until its hits read clean.
3. **Try it.** `commandments rule try <file> <path>` runs one rule over a folder and prints each match;
   `--work` counts what it cost.
4. **Prove it in the fixtures** ([[detector-fixtures]]): mark ≥3 DIVERSE examples in every language the rule
   judges, a righteous twin it must not flag, and a fixed twin. `commandments rule prove --all` and
   `fixture/rules_test.go` check every rule against every fixture project.
5. **Calibrate on real code**: `bin/commandments judge ../some-app/src --sin=your-sin --no-checklist`, and
   read every hit. A real false positive tightens the `find` with a principled check, or drops the
   language from `engine`.

## Pointing at the skill

`sin.skill` names the skill that teaches the fix, `sin.description` the one line the docs project from;
`judge` prints that skill, so the agent reads one skill and resolves the whole group. The generated "when it
fires" rows regenerate from the rule files (`composer sins`, or the pre-commit hook).

## Related

- [[detector-engine]] · [[detector-fixtures]] · [[writing-exemptions]]
- Commit conventions in [[releasing]].
