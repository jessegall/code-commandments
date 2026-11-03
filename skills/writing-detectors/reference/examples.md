# Rules that work, one per kind of question

Every rule here is real: a test parses each one and refuses this file if any of them stops being a rule the
tool can run. Copy one and change what it asks, rather than starting from an empty file — `commandments make
<Name>` scaffolds the plumbing and `commandments rule schema` is what your editor completes from.

The `skill` each one names is a shipped slug so the examples stand alone. Point yours at a skill of your own.

## By what a node IS

The first question to reach for, because it survives a rename.

```json
{
    "engine": "php",
    "sin": {
        "name": "query-in-a-loop",
        "description": "A query runs once per item, so a list of ten costs ten round trips.",
        "rule": "Load what the loop needs before it, in one query.",
        "skill": "php/absence"
    },
    "find": {
        "select": "call",
        "where": [
            { "withinLoop": true },
            { "of": "receiver", "resolvesLike": "*Repository" }
        ]
    }
}
```

## By the type a value HAS

What a name cannot see: the value copied into a variable first.

```json
{
    "engine": "python",
    "sin": {
        "name": "whole-line-read",
        "description": "Code reads the private source line instead of asking the line about its parts.",
        "rule": "Ask an aggregate on the line, or add one.",
        "skill": "python/absence"
    },
    "find": {
        "select": "member-access",
        "where": [
            { "nameMatches": "^_" },
            { "of": "receiver", "typeLike": "*ShellLine" }
        ],
        "reject": [{ "of": "receiver", "is": "self-reference" }]
    }
}
```

## One rule for several languages

Neutral words only, and `body` rather than `child`, so the shape of a body does not decide the answer.

```json
{
    "engine": ["python", "go", "csharp"],
    "sin": {
        "name": "loop-wrapped-in-if",
        "description": "A loop whose whole body sits under one condition.",
        "rule": "Flip the condition and continue, so the work sits at the loop's own level.",
        "skill": "python/flow"
    },
    "find": {
        "select": "loop",
        "where": [{ "body": { "is": "branch" } }]
    }
}
```

## By where the code SITS

A path is the honest way to say "this layer may not do that".

```json
{
    "engine": "php",
    "sin": {
        "name": "sql-outside-a-repository",
        "description": "SQL written outside the folder that owns the tables.",
        "rule": "Queries go through the repository that owns the table.",
        "skill": "php/absence"
    },
    "find": {
        "select": "call",
        "where": [{ "name": "select" }],
        "reject": [{ "fileMatches": "(^|/)app/Repositories/" }]
    }
}
```

## By how much there is of it

A count says what a reader feels: too much in one place.

```json
{
    "engine": "typescript",
    "sin": {
        "name": "too-many-parameters",
        "description": "A function taking more arguments than a caller can keep in mind.",
        "rule": "Pass the object the arguments came from.",
        "skill": "typescript/absence"
    },
    "find": {
        "select": "function",
        "where": [{ "parameters": { "atLeast": 5 } }]
    }
}
```

## By what the compiler knows and a reader cannot see

Here the tree alone is not enough, and the resolver earns its keep.

```json
{
    "engine": "csharp",
    "sin": {
        "name": "awaited-in-a-loop",
        "description": "Work awaited once per item, so a list of ten waits ten times in turn.",
        "rule": "Start the work for every item, then await them together.",
        "skill": "csharp/absence"
    },
    "find": {
        "select": "kind:AwaitExpression",
        "where": [{ "withinLoop": true }]
    }
}
```

## One rule built on another

`from` narrows what another rule found, so the shared half is written once.

```json
{
    "engine": "php",
    "sin": {
        "name": "sql-in-a-controller",
        "description": "SQL written in a controller.",
        "rule": "Queries go through the repository that owns the table.",
        "skill": "php/absence"
    },
    "find": {
        "from": "SqlOutsideARepository",
        "where": [{ "fileMatches": "(^|/)app/Http/Controllers/" }]
    }
}
```
