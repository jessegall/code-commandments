---
name: commandments-writing-detectors
description: "How to write a commandment of your OWN — a project-local rule that judges every file from then on. Read this BEFORE writing or changing a rule in `.commandments/custom/`, when the user asks for a new rule/check/detector, when `commandments make` points you here, or when you are about to reach for a regex to inspect code."
---

# Writing your own commandments

> The shipped rules are not the ceiling. A convention you keep restating in review is a rule you
> haven't written yet. Write it once, and it judges every file from then on.

## A rule is a question the engine answers

A rule of your own is data the binary runs — a `.json` file in `.commandments/custom/` — and it asks
the same engine every shipped rule asks. It names the engine it judges, the sin it finds, and a query:
a **selector** that opens it, then **`where`** steps that keep a node and **`reject`** steps that drop
one. **Each step makes exactly one check**; a rule is only as good as the question each step asks.

```json
{
    "engine": "backend",
    "sin": {
        "name": "raw-sql",
        "description": "SQL written inline at a call site.",
        "rule": "Queries go through the repository that owns the table.",
        "skill": "no-raw-sql"
    },
    "find": {
        "select": "call",
        "where": [
            {"resolves": "Illuminate\\Support\\Facades\\DB", "of": "child:class"},
            {"nameIn": ["select", "statement", "unprepared"]}
        ],
        "reject": [
            {"file": "*Repository.php"}
        ]
    }
}
```

**The selector** is a neutral kind, which reads the same in every language — `call`, `construction`,
`function`, `type-declaration`, `assignment`, `loop`, `branch`, `return`, `throw`, `catch`,
`comparison`, `literal`, `member-access`, `null-safe`, `identifier`, `import`, `parameter`, `block`,
`bail-out`, `expression-statement`, `self-reference` — or `kind:<Kind>`, a language's own node kind
(`kind:Expr_MethodCall`) when no neutral kind says it.

**A step's one check** — every one there is, generated from the tool itself:

<!-- BEGIN: rule-checks (auto-generated, run `composer sins`) -->
**What it is and what it is called**

| Check | Keeps the node when |
|---|---|
| `{"is": "loop"}` | it answers the neutral kind |
| `{"kind": "Expr_StaticCall"}` | it is the language's own kind |
| `{"name": "select"}` | its name — a call's or an access's is its name child's — is it |
| `{"nameIn": ["select", "statement"]}` | its name is one of them |
| `{"nameLike": "get*"}` | its name matches the glob, `*` any run of characters and `?` one |
| `{"nameMatches": "^(get\|set)[A-Z]"}` | its name matches the regular expression |
| `{"nameCase": "snake"}` | its name, less a leading `$` or `_`, is written `camel`, `pascal`, `snake`, `upper` or `kebab` case |
| `{"hasModifier": "static"}` | it carries the modifier |
| `{"hasFlag": "byRef"}` | it carries the flag its language's tree sets |

**What it says**

| Check | Keeps the node when |
|---|---|
| `{"text": "select * from users"}` | a literal's text is exactly it |
| `{"textLike": "*where id*"}` | a literal's text matches the glob |
| `{"textMatches": "(?i)^select"}` | a literal's text matches the regular expression |
| `{"commentLike": "*TODO*"}` | a comment on it, or in the run of comments directly above it, matches the glob |
| `{"commentMatches": "(?i)\\b(TODO\|FIXME)\\b"}` | a comment on it, or in the run of comments directly above it, matches the regular expression |
| `{"docTag": "deprecated"}` | its documentation carries the tag: `@tag` in PHPDoc and JSDoc, an XML element in C#, a Sphinx field or directive or a Google/NumPy section in a Python docstring |
| `{"documented": false}` | it carries a doc comment, or not with `false` |

**What it refers to**

| Check | Keeps the node when |
|---|---|
| `{"resolves": "App\\Models\\User"}` | what its name refers to, resolved, is that |
| `{"resolvesLike": "App\\Models\\*"}` | what its name refers to, resolved, matches the glob |
| `{"calls": {"name": "dd"}}` | a call of its own — not one inside a function nested in it — passes the step |
| `{"argument": {"at": 0, "is": "literal"}}` | the argument at that place passes the step; a negative `at` counts from the end, `-1` the last, and none there passes nothing |
| `{"constructs": "Date*"}` | a construction, or on anything else one of its own, creates an instance of a type the glob names — in Python, a call of a class |
| `{"unused": true}` | nothing refers to it: a function or type from outside itself, through the names and calls the scan resolves and its language's call graph; a parameter, by its name read in its function |
| `{"calledFrom": "app/Http/*"}` | something referring to it — a call, most often — sits in a file the glob matches |

**Where it sits**

| Check | Keeps the node when |
|---|---|
| `{"file": "*Repository.php"}` | its file, or any tail of the path, matches the glob |
| `{"namespaceLike": "App\\Http\\*"}` | the namespace, package or module it is declared in matches the glob |
| `{"layer": "App\\Domain"}` | it sits in that layer of the stack the project declares (backend, Python, C#) |
| `{"testCode": true}` | it is test code, as its bridge marks it or its language names test files |
| `{"topLevel": true}` | it sits outside every function, closures too, and every type — code that runs when its file loads |
| `{"withinLoop": true}` | it sits inside a loop, or not with `false` |
| `{"position": "first"}` | it is the `first`, `last` or `only` one of its siblings (an only one is also first and last) |
| `{"descendant": {"is": "return"}}` | some node inside it passes the step |
| `{"inside": {"is": "catch"}}` | some node above it, up to the file's root, passes the step |
| `{"next": {"is": "return"}}` | the sibling right after it passes the step |
| `{"previous": {"is": "branch"}}` | the sibling right before it passes the step |
| `{"nestedAtLeast": {"is": "loop", "count": 3}}` | it and the nodes above it that pass the step number at least `count` |

**How big it is**

| Check | Keeps the node when |
|---|---|
| `{"parameters": {"atLeast": 5}}` | a function declares that many parameters — a variadic, a defaulted one and Python's `self` count once each |
| `{"arguments": {"atLeast": 4}}` | a call or construction is handed that many arguments — a named or a spread one counts once |
| `{"lines": {"atLeast": 40}}` | it spans that many lines, its first and last among them |
| `{"members": {"is": "function", "atLeast": 20}}` | a type declares that many members directly that pass the step (every member, with no check) |
| `{"complexity": {"atLeast": 10}}` | one plus every branch, loop and catch in it, as its language marks them, is that many — a switch is one branch, `&&` and `\|\|` are not counted, and a function inside it is its own count |
| `{"count": {"descendant": {"is": "return"}, "atLeast": 4}}` | that many of its descendants pass the step — or `"child"`, with `"field"` to count one field's children |
| `{"duplicated": {"atLeast": 2}}` | that many functions of the codebase, it among them, have its body, read from the tree, blind to spacing and comments |

**Its type**

| Check | Keeps the node when |
|---|---|
| `{"typeKind": "interface"}` | a type declaration declares a `class`, `interface`, `enum`, `trait`, `record`, `struct` or `protocol` |
| `{"extends": "Controller"}` | a type declaration names the type among the ones it extends directly |
| `{"extendsAny": "Exception"}` | the type is anywhere in its chain of parents, followed through the scan and the declarations outside it the language knows |
| `{"implements": "ShouldQueue"}` | it honours the contract: its own, its parents', and the contracts those extend |
| `{"hasAnnotation": "Route"}` | a declaration carries the attribute or decorator |
| `{"returnType": "?*"}` | a function's written return type matches the pattern, as written or as the type it names resolves |
| `{"parameterType": "array"}` | a parameter's written type matches the pattern, as written or as the type it names resolves |

**Its language's own**

| Check | Keeps the node when |
|---|---|
| `{"php": "facadeCall"}` | PHP's own check of the name holds — one of the checks listed below |
| `{"python": "constructor"}` | Python's own check of the name holds |
| `{"csharp": "inherited"}` | C#'s own check of the name holds |
| `{"typescript": "optional"}` | TypeScript's own check of the name holds |
| `{"vue": "component"}` | a Vue component's own check of the name holds — its template's, and its script's TypeScript ones |

**Each language's own checks**, named under its key — a small set that grows on request, each name kept when the code behind it changes:

| Check | Keeps the node when |
|---|---|
| `{"php": "coalesce"}` | it is a `??` expression |
| `{"php": "constructor"}` | it declares a constructor |
| `{"php": "facadeCall"}` | it is a static call on a Laravel facade |
| `{"php": "inDataClass"}` | it sits in a Spatie Data class |
| `{"php": "inNamedConstructor"}` | the function around it builds an instance of its own class |
| `{"php": "inPageObject"}` | it sits in a Data class that is a page object |
| `{"php": "returnedValue"}` | it is the value a return statement returns |
| `{"php": "typeNarrowingGuard"}` | it is an outermost `&&` of two or more instanceof checks |
| `{"python": "constructor"}` | it is a class's __init__ |
| `{"python": "evaluated"}` | it is an expression the code evaluates, outside every type annotation |
| `{"python": "inNamedConstructor"}` | it sits in a named constructor, where loose data becomes the class |
| `{"python": "returnedValue"}` | it is what a return statement returns |
| `{"python": "typeNarrowingGuard"}` | it is an outermost `and` of two or more isinstance checks |
| `{"csharp": "buildingAnObject"}` | it feeds straight into an object being created in the same function |
| `{"csharp": "inNamedConstructor"}` | the function around it builds an instance of its own type |
| `{"csharp": "inOverride"}` | it sits in a member that overrides or implements a contract, whose signature the contract decided |
| `{"csharp": "inherited"}` | the member overrides or implements another, decided against the whole hierarchy |
| `{"typescript": "absence"}` | it is the literal null or undefined |
| `{"typescript": "optional"}` | a field or parameter may be missing: written `x?`, or typed to admit null or undefined |
| `{"vue": "absence"}` | it is the literal null or undefined |
| `{"vue": "component"}` | the element's tag names a component: it starts upper-case |
| `{"vue": "optional"}` | a field or parameter may be missing: written `x?`, or typed to admit null or undefined |
| `{"vue": "templateRoot"}` | the element is the template's only top-level element |

**What `"of"` can name**

| Target | The step judges |
|---|---|
| `parent` | the node whose children hold it |
| `enclosingFunction` | the named function it sits in; a closure is passed over |
| `enclosingType` | the type declaration it sits in |
| `closest:<kind>` | the nearest node above it of the neutral kind, such as `closest:loop` |
| `root` | its file's root |
| `child:<field>` | its child filling the field, such as `child:class` |
<!-- END: rule-checks -->

A **type name** — in `extends`, `extendsAny`, `implements` and `hasAnnotation` — is matched by the symbol it
resolves to when it is qualified (`App\Http\Controller`, `shop.models.Base`, `N.IOther`), and by its last
part when it is bare (`Controller`). A type the scan cannot resolve is matched by what it says. In C#,
`Serializable` also names `SerializableAttribute`. Python declares no contracts apart from its bases, so
`implements` there reads the class's whole chain of parents, where its ABCs and Protocols are. A **type
pattern** has one wildcard, `*`: a `?` is itself, so `?*` is PHP's nullable type, and a function or
parameter with no type written matches no pattern — reject `"*"` to find the untyped ones.

`unused` knows only the code it scans: a controller action a route calls, a listener the framework
dispatches, a method a parent type declares, or a function another project imports all look unused, so
pair it with a `reject` for them (`{"hasAnnotation": ...}`, `{"extendsAny": ...}`, `{"file": ...}`).

**Test code** is a file C#'s bridge marks as a test project's, and elsewhere a file the language's test
runner collects: PHP's `*Test.php`, Python's `test_*.py`, `*_test.py` and `conftest.py`, a frontend
`*.test.*` or `*.spec.*`, and in every language a file under a `tests` or `test` folder (`__tests__`
too, in the frontend). C# deprecation is the `[Obsolete]` attribute, which `hasAnnotation` finds, and a
Python docstring is no comment, so `commentLike` does not read one: `docTag` does.

Every size takes `atLeast`, `atMost` or both, and both are inclusive.

A node's **siblings** fill the same field of the same parent: a statement's are the other statements
of its block, an argument's the call's other arguments. `nestedAtLeast` counts through closures, since
nesting is what a reader sees: a loop in a closure in a loop is two loops deep.

A glob matches the whole name: `*` is any run of characters, `?` one, and a backslash is itself, so a
PHP class is written as it reads. A regular expression is Go's (RE2) and matches anywhere unless it is
anchored. A `layer` step reads the layers the config declares for the engine's
`NamespaceDependencyDetector` (`commandments layers` proposes them), so a rule can speak of a layer
without repeating its namespaces; with none declared it finds nothing.

A rule the binary cannot run says why when `judge` starts — the file, and which part is wrong.

## Scaffold it — don't hand-write the plumbing

```bash
vendor/bin/commandments make NoRawSql                    # a backend rule and a skill of its own
vendor/bin/commandments make NoRawSql --engine=python     # another engine
vendor/bin/commandments make NoRawSql --skill=absence     # taught by a skill that already exists
vendor/bin/commandments make NoDebug --from=no-debug-calls # start from a ready rule and adapt it
```

The ready rules are `no-debug-calls`, `no-todo-comments`, `max-parameters`, `max-function-length`,
`max-nesting` and `no-sql-in-controllers`, each written for every engine it makes sense in. One is a
starting point, not a verdict: read its query, change the numbers and names to the project's, and prove
it on samples of your own.

That writes `NoRawSqlDetector.json` and, when the skill is new, `skills/no-raw-sql/SKILL.md` into
`.commandments/custom/`, turns the rule on under `detectors` in `.commandments/config.json`, and prints
the rest of the process. The folder is kept out of the `.commandments/` gitignore: they are your rules,
so commit them.

They are named as yours wherever they fire: `judge` prints `[NoRawSqlDetector (custom)]` in the
console and in `sins.md`, `judge --list` lists them so, and `report --detector` refuses them — nothing
upstream can fix a rule the package does not ship.

## The anatomy — a rule, a sin, a skill

**The skill teaches.** `skills/<slug>/SKILL.md` is what a finding sends the reader to, published as you
write it into the project's skill library as `commandments-<slug>`. Its front matter says when to load
it (`description`), what the briefing lists (`summary`), how often it comes up (`tier: mandatory` or
`keep-in-mind`) and which languages it teaches (`languages: [php]`).

**The sin names.** Inside the rule: a `name` (the `--sin=` id), the `skill` it points at by slug — your
own, or a shipped one such as `backend/absence` — the `description` (the symptom) and the `rule` (the
positive directive the fix follows).

**The query finds.** Nothing else. It has no fix logic, and it knows nothing about the report.

## Classify by what the code IS, never by what it is CALLED

The cardinal rule. Derive the answer from what a node is and what its name resolves to — the neutral
kind, the language's kind, the class a call reaches. **Never** from a class, method or variable name, a
suffix, or a hardcoded list. The name, text and `file` checks exist because a call has to be named at some
point; reach for them last, and never as a list of the exceptions you happened to meet. Names lie, and a
rule built on one fires on the wrong code the first time somebody renames well.

## See what a rule reads before you write it

A query names what the tree holds, so read the tree first:

```bash
vendor/bin/commandments rule explain app/Http/OrderController.php --line=42
```

prints every node starting on line 42 — its kind, the neutral kinds it answers, the field it fills, its
name, its text and what it resolves to — which is exactly what `select` and each check can see. Then
watch the query while you write it, without turning the rule on:

```bash
vendor/bin/commandments rule try NoRawSql app/
```

## Prove it fires — a rule nobody has watched is a guess

A rule that parses is not a rule that works. **Write samples**: files under
`.commandments/custom/samples/` holding every form you mean to catch **and the near-misses you must NOT
catch**, each marked by a comment above it:

```php
// .commandments/custom/samples/RawSql.php
// @sin NoRawSqlDetector
DB::select('select * from orders');
// @sin NoRawSqlDetector
DB::unprepared($sql);
// @righteous NoRawSqlDetector
Order::query()->where('id', $id)->get();
// @righteous NoRawSqlDetector
$this->select($columns);
```

```bash
vendor/bin/commandments rule prove
```

holds every rule to its marks: it fails a rule that misses a `@sin`, flags a `@righteous` twin or a
`@fixed` fix, or flags anything unmarked. The samples stay — they are the rule's spec, and `rule prove`
runs again whenever the rule changes. The near-misses are the whole point: a rule that fires on
everything is not a rule.

## Calibrate on real code — before you trust it

A green probe proves it *can* fire; it does not prove it is *right*. Run it over your actual source —
scoped with `--changes` or `--branch` — and **read the hits by eye**, judging each **against the skill**,
never against what the code happens to do today.

- **Volume proves nothing.** 400 hits can be 400 real sins. A widespread pattern is not "convention"
  that excuses a finding. Do not soften a rule because it fires a lot.
- **Only a genuine false positive invalidates it** — code that is *correct under your architecture* yet
  gets flagged. Then tighten with a **principled `reject`** (a check on what the code is), never a name
  list.
- **Some ideas die here.** If no check separates the sin from a legitimately valid look-alike — if the
  difference is only the author's intent — the rule is not viable. Cut it. That is a successful
  outcome, not a failure.

## It does not auto-fix

A rule of your own reports; it never rewrites. If the honest fix depends on what the code MEANS —
which value object to introduce, where absence really belongs — an auto-fix would launder the problem
instead of solving it. Let the skill teach the reader.

## The cadence, in order

1. **Load this skill** — before writing a line.
2. `commandments make <Name>` — scaffold the rule, and the skill when it is new.
3. **Write the teaching first.** If you can't state what good looks like, the rule doesn't know what
   it's looking for either.
4. Name the sin: the symptom, and the rule as a positive directive.
5. Write the query — a selector, then one check per step — reading the tree with `rule explain` and
   watching it with `rule try`.
6. **Prove it.** Samples marking every form you mean to catch and the near-misses, then `rule prove`.
7. **Calibrate** on real code. Tighten, or cut.
8. `vendor/bin/commandments sync` — publishes your skill so the agent can load what the finding points
   at.

## Reference

<!-- BEGIN: commands:make,rule (auto-generated, run `composer sins`) -->
| Command | Does |
|---|---|
| `commandments make <Name>` | scaffold a backend (PHP) commandment and turn it on |
| `commandments make <Name> --engine=frontend` | scaffold a frontend (Vue) one instead |
| `commandments make <Name> --engine=typescript` | scaffold a TypeScript one instead |
| `commandments make <Name> --engine=python` | scaffold a Python one instead |
| `commandments make <Name> --engine=csharp` | scaffold a C# one instead |
| `commandments make <Name> --skill=NAME` | point the sin at an EXISTING skill (shipped or your own) instead of writing a new one |
| `commandments make <Name> --from=<template>` | start from a ready rule to adapt: max-function-length, max-nesting, max-parameters, no-debug-calls, no-sql-in-controllers, no-todo-comments |
| `commandments rule explain <file> [--line=N]` | print the tree a rule reads in the file: every node's line, kind, neutral kinds, field, name and what it resolves to |
| `commandments rule try <Rule> <path>` | run one rule over the path without turning it on, and print each match with its line |
| `commandments rule prove [path]` | check every rule of the project flags exactly the code its samples mark, and nothing else |
| `commandments rule schema` | print the JSON Schema of a rule file |

<!-- END: commands:make,rule -->
