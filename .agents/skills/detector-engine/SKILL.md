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

## Counting what is known

A `filter` whose check is unknown for one item is unknown as a whole, and so is its `count`: one slot whose type the
tree cannot place makes "two slots name Data" unknown for the whole class. Count only what is proven, with
`["filter", list, "x", ["proven", check]]`, wherever an unknown item must not hide the known ones.

## What a judging keeps

A definition sees only its own arguments, so the evaluator keeps its answer for the whole judging by the values it is
handed: a node, a name, a number. One that reads the whole program (`referrers`, `overriders`, `subtypes`,
`programEnums`, `programTexts`, or a definition that calls one) or is written `"kept": true` is kept from its first
answer; any other from the second time it is asked with the same values. A definition handed a list or a type is not
kept, so hand it the node the list comes from.

So work out what repeats per site once, in a definition taking the node it depends on, and write it `"kept": true`:
a callee's calls as `callsOf(callee)`, a function's assignments as `assignmentsOf(fn)`, a class's field names as
`fieldNames(type)`. Have it give back what the rule needs (names, a count, the few nodes of one kind), so each site
reads that answer and does not walk the body again. `["groupItems", nodes, "n", "$n.kind"]` sorts a body by kind in
one pass.

## A rule goes over a file a few times, never once per site

The engine counts how many nodes of each file a rule's lists hand it. A rule handed one file's nodes more than 25
times over is stopped and skipped, and the run says which rule and which file; every other rule still runs. It is
what a scan inside a loop looks like: for every call, every other call of its callee; for every assignment, the whole
function; for every element, the whole template. `commandments rule try <file> <path> --work` prints the most times
the rule went over one file, so read that number on a real project before a rule ships, and bring it towards two.

## Testing

`commandments rule try <file> <path> --work` runs one rule and counts its cost, with the most times it went over
one file; `fixture/rules_test.go`
proves every rule on every fixture project; a rule's own edge cases go in `tests/rules/<language>`. Run Go
only through `scripts/dev`, scoped to the package you touched.

## Related

- [[writing-detectors]]: how to author a rule over this engine.
- [[detector-fixtures]]: the self-checking fixture that proves it.
