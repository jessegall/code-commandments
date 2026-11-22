# Rules that work, one per kind of question

Every rule here is real: a test reads each one and refuses this file if any of them stops being a rule the
tool can run. Copy one and change what it asks, rather than starting from an empty file — `commandments make
<Name>` scaffolds the plumbing and `commandments rule schema` is what your editor completes from.

The `skill` each one names is a shipped slug so the examples stand alone. Point yours at a skill of your own.

## By what a node IS

The first question to reach for, because it survives a rename: a call that runs inside a loop of its own
function.

```json
{
    "$schema": "commandments:neutral-rule/v2",
    "engine": "php",
    "sin": {
        "name": "repository-call-in-a-loop",
        "description": "A repository is asked once per item, so a list of ten costs ten round trips.",
        "rule": "Load what the loop needs before it, in one query.",
        "skill": "php/absence"
    },
    "find": {
        "select": "call",
        "as": "c",
        "where": ["and",
            ["exists", ["enclosing", "$c", "loop", "function"]],
            ["proven", ["isSubtype", ["typeOf", "$c.callee.subject"], "App\\Repositories\\Repository"]]],
        "emit": "$c"
    }
}
```

## By the type a value HAS

What a name cannot see: the value copied into a variable first.

```json
{
    "$schema": "commandments:neutral-rule/v2",
    "engine": "python",
    "sin": {
        "name": "whole-line-read",
        "description": "Code reads the private source line instead of asking the line about its parts.",
        "rule": "Ask an aggregate on the line, or add one.",
        "skill": "python/absence"
    },
    "find": {
        "select": "access",
        "as": "a",
        "where": ["and",
            ["matchesText", "$a.member.name", "^_"],
            ["proven", ["isSubtype", ["typeOf", "$a.subject"], "shell.ShellLine"]],
            ["not", ["proven", ["=", "$a.subject.form", "self"]]]],
        "emit": "$a"
    }
}
```

## One rule for several languages

Every language writes the same tree, so a loop's body is a block of statements whatever the syntax.

```json
{
    "$schema": "commandments:neutral-rule/v2",
    "engine": ["python", "go", "csharp", "php", "typescript"],
    "sin": {
        "name": "loop-wrapped-in-if",
        "description": "A loop's whole body is one if.",
        "rule": "Skip the items that do not qualify with a guard that continues, or filter them first.",
        "skill": "shared/flow"
    },
    "find": {
        "select": "loop",
        "as": "l",
        "where": ["=", ["count", "$l.body.statements"], 1],
        "let": [["only", ["at", "$l.body.statements", 0]]],
        "having": ["and", ["=", "$only.kind", "if"], ["not", ["exists", "$only.else"]]],
        "emit": "$l"
    }
}
```

## By where the code SITS

A fact about the place: the class around a string, and what that class extends.

```json
{
    "$schema": "commandments:neutral-rule/v2",
    "engine": "php",
    "sin": {
        "name": "sql-in-a-controller",
        "description": "A controller writes an SQL statement itself.",
        "rule": "Query through the model or a repository, and keep the controller to the request and the response.",
        "skill": "php/dependency-direction"
    },
    "find": {
        "select": "literal",
        "as": "s",
        "where": ["and",
            ["=", "$s.literalKind", "string"],
            ["matchesText", "$s.value", "(?i)^\\s*(select|insert|update|delete)\\s"],
            ["some", ["knownSupertypes", ["enclosing", "$s", "type"]], "t", ["=", "$t", "Illuminate\\Routing\\Controller"]]],
        "emit": "$s"
    }
}
```

## By how much there is of it

A count, with its limit a parameter the project's config can change.

```json
{
    "$schema": "commandments:neutral-rule/v2",
    "engine": ["php", "python", "go", "csharp", "typescript"],
    "sin": {
        "name": "too-many-parameters",
        "description": "A function declares more parameters than the project allows.",
        "rule": "Give the values that travel together a type of their own, and pass that.",
        "skill": "shared/arguments"
    },
    "parameters": {
        "limit": {"type": "number", "default": 5, "description": "How many parameters are too many."}
    },
    "find": {
        "select": "function",
        "as": "f",
        "where": [">=", ["count", "$f.parameters"], "$param.limit"],
        "emit": "$f"
    }
}
```

## By what the compiler knows and a reader cannot see

The declaration a call reaches, found by the resolver, not by the name written at the call.

```json
{
    "$schema": "commandments:neutral-rule/v2",
    "engine": ["php", "python", "go", "csharp", "typescript"],
    "sin": {
        "name": "production-calls-test-code",
        "description": "Production code calls a function declared in a test file.",
        "rule": "Move what both need into production code, and let the test call it there.",
        "skill": "shared/dependencies"
    },
    "find": {
        "select": "call",
        "as": "c",
        "where": ["not", "$c.file.test"],
        "let": [["target", ["declarationOf", "$c"]]],
        "having": ["proven", "$target.file.test"],
        "emit": "$c"
    }
}
```

## One check named once, and called by name

A check two rules need is a definition: written once in one rule, and called by another as
`<Rule>.<definition>`.

```json
{
    "$schema": "commandments:neutral-rule/v2",
    "engine": ["php", "python", "go", "csharp", "typescript"],
    "sin": {
        "name": "todo-in-a-function",
        "description": "A function carries a TODO or FIXME left for later.",
        "rule": "Do the work now, or file it where work is tracked and delete the comment.",
        "skill": "shared/documentation"
    },
    "definitions": {
        "isTodo": {"args": ["k"], "expr": ["matchesText", "$k.text", "(?i)\\b(TODO|FIXME)\\b"]}
    },
    "find": {
        "select": "function",
        "as": "f",
        "where": ["some", ["commentsOf", "$f"], "k", ["isTodo", "$k"]],
        "emit": "$f"
    }
}
```

A rule in a file named `TodoInAType.json` then asks the same of a type: `["some", ["commentsOf", "$t"], "k",
["TodoInAFunction.isTodo", "$k"]]`.
