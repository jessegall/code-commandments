---
name: detector-engine
description: What a version 8 rule reads and how — the generic tree's kinds, roles and facts (contract/CONTRACT.md), the rule evaluator's find (select, where, let, having, emit), pipelines over program relations, shared definitions, and WHERE a missing fact belongs (the bridge, never the rule). Read this when writing or changing a rule, or when you reach for a fact the tree doesn't have yet.
---

# The engine a rule reads

Every language reaches the engine the same way: its bridge parses it with the language's own parser and writes
the generic tree (`contract/CONTRACT.md`), and `engine.Load` holds every stream as one `engine.Codebase`. A rule
never touches a parser: its `find` is evaluated over that tree by `rule/evaluate.go`.

```jsonc
"find": {
  "select": ["if", "loop"],          // the neutral kinds a match starts from
  "as": "s",                         // the name the node is bound to
  "where": ["choice", "$s"],         // checks on the node, one per line of an "and"
  "let": [["depth", ["count", ["ownAncestors", "$s"]]]],
  "having": [">=", "$depth", "$param.limit"],
  "emit": "$s"                       // the node the finding points at
}
```

## What a node answers

A node has a **kind** (`if`, `loop`, `call`, `access`, `catch`, `function`, `class`, …), **named roles**
(`$b.then`, `$b.else`, `$c.callee`, `$c.callee.member`, `$f.body`) and **facts**: its name, text, type,
the symbol it resolves to, its module, its file (`$s.file.language`, `$s.file.test`). `contract/CONTRACT.md`
lists every kind and role, with worked examples. A missing role reads as absent, never as an error, so
`$b.else.kind` needs no check before it.

`commandments rule explain <file> --line=N` prints the tree a rule reads at that line; `commandments rule
schema` prints every operator: logic (`and`, `or`, `not`, `case`), lists (`some`, `every`, `none`, `filter`,
`map`, `count`, `first`, `last`), navigation (`ownAncestors`, `siblings`, `before`, `encloses`), resolution
(`declarationOf`, `referrers`, `typeDeclaration`, `assignable`) and text (`startsWith`, `snakeCase`,
`baseName`).

## Where a missing piece goes

1. **A check several rules share** goes into `definitions` of a shared file under `rules/definitions/`
   (`tree.json` holds the `tree.*` reads every rule calls: `tree.effective`, `tree.nullish`, `tree.kindOf`).
2. **A package's knowledge** (Laravel, Spatie Data, …) is data in its own file there (`laravel.json`,
   `spatie.json`), never written into a general rule.
3. **A whole-program question** reads a program relation through a pipeline: `"from": "imports"` with
   `"pipeline": [{ "where": … }, { "groupBy": … }, { "join": … }]`. The relations are built once per codebase
   in `rule/program.go`: `imports`, `declarations`, `references`, `calls`, `inherits`, `overrides`, `files`,
   `divergentTwins`.
4. **A fact the tree does not carry** (a resolved type, an outside symbol, a doc comment's tags) is added to
   the bridge that parses the language and to the contract. A language supplies plain facts only; it never
   decides a rule, and a rule never guesses a fact back from text.
5. **A rule whose fix needs a package or a language version** names it in `sin.requires`
   (`{"ecosystem": "composer", "name": "php", "version": "8.5"}`); a project below that version does not run it.
6. **A finding's advice** (`find.advice`) reads the same relations the find does, so it can name the other side
   of what it found (`rowsOf`, `rowOf`); a node's `file.fromProject` is the path the report shows.

> Smell test: if you are about to compare a name with a list, or `startsWith` a class name, stop: the tree's
> kind, role or resolved type already answers it. A name check is a smell to justify, not a default.

## Testing

`commandments rule try <file> <path> --work` runs one rule and counts its cost; `fixture/rules_test.go`
proves every rule on every fixture project; a rule's own edge cases go in `tests/rules/<language>`. Run Go
only through `scripts/dev`, scoped to the package you touched.

## Related

- [[writing-detectors]]: how to author a rule over this engine.
- [[detector-fixtures]]: the self-checking fixture that proves it.
