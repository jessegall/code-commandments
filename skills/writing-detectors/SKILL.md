---
name: commandments-writing-detectors
description: "How to write a commandment of your OWN — a project-local rule that judges every file from then on. Read this BEFORE writing or changing a rule in `.commandments/custom/`, when the user asks for a new rule/check/detector, when `commandments make` points you here, or when you are about to reach for a regex to inspect code."
---

# Writing your own commandments

> The shipped rules are not the ceiling. A convention you keep restating in review is a rule you
> haven't written yet. Write it once, and it judges every file from then on.

## A rule is a question the engine answers

A rule of your own is data the binary runs — a `.json` file in `.commandments/custom/`, in that folder or any
folder under it, so a project with rules for several parts of its code groups them by what they protect
(`custom/core/`, `custom/viewer/`) — and it asks the same engine every shipped rule asks. A rule is named by its
FILE wherever it sits, so moving one into a folder leaves every mention of it in the config standing, and two files
of one name are both refused rather than one of them quietly winning. It names the engine it judges, the sin it finds, and a query:
a **selector** that opens it, then **`where`** steps that keep a node and **`reject`** steps that drop
one. **Each step makes exactly one check**; a rule is only as good as the question each step asks.

```json
{
    "engine": "php",
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

**A rule is built from other rules.** A check you have already written once is named, never written
again: `{"rule": "ActionClass"}` passes when the rule of that name finds the node too, and a `find`
that writes `"from": "ActionClass"` in place of `"select"` narrows what that rule found, which is one
rule read inside another. Both reach any rule of the same engine in `.commandments/custom/`, and every
part stays a rule of its own, listed and run on its own terms. A name nothing declares, a rule of
another engine and a chain that comes back to itself are refused when the rules are read, before
anything is judged.

**Say `either` and `not` without a second rule.** `{"any": [{"is": "loop"}, {"is": "branch"}]}` passes
when one of its steps does, `{"all": [...]}` when every one does, and `{"not": {...}}` when its step
does not — which is how a `where` says what only a `reject` could.

**A step's one check** — every one there is, generated from the tool itself:

<!-- BEGIN: rule-checks (auto-generated, run `composer sins`) -->
**What it is and what it is called**

| Check | Keeps the node when |
|---|---|
| `{"is": "loop"}` | it answers the neutral kind |
| `{"kind": "Expr_StaticCall"}` | it is the language's own kind, written as the language writes it or as the vocabulary spells it: `Expr_StaticCall` and `static-call` are one construct. The spellings are derived from the kinds, so the editor's own completion lists what a language really emits, and a spelling two kinds share is the spelling of neither |
| `{"name": "select"}` | its name — a call's or an access's is its name child's — is it |
| `{"nameIn": ["select", "statement"]}` | its name is one of them |
| `{"nameLike": "get*"}` | its name matches the glob, `*` any run of characters and `?` one |
| `{"nameMatches": "^(get\|set)[A-Z]"}` | its name matches the regular expression |
| `{"nameMood": "question"}` | its name is in that linguistic mood: `bare-verb` is the verb and nothing else (`holds`, `ships`), `third-person` narrates a verb (`entersTestMode`), and `question` opens with a question word (`is`, `has`, `can`, `should`). It reads the mood, never a list of words a rule writes. It is the one check here that judges a NAME, and it answers only whether the name READS a certain way: what makes that a sin is the mood against what the thing DOES, so pair it with what the member answers or whether it changes anything, as the shipped rules do |
| `{"nameCase": "snake"}` | its name, less a leading `$` or `_`, is written `camel`, `pascal`, `snake`, `upper` or `kebab` case |
| `{"hasModifier": "static"}` | it carries the modifier |
| `{"hasFlag": "byRef"}` | it carries the flag its language's tree sets |
| `{"operator": "*"}` | its operator is exactly it: `*` and `??` tell two binary expressions apart, `+=` an assignment, `!` a unary one, `extends` a heritage clause |
| `{"operatorIn": ["*", "/"]}` | its operator is one of them |

**What it says**

| Check | Keeps the node when |
|---|---|
| `{"text": "select * from users"}` | a literal's text is exactly it |
| `{"textLike": "*where id*"}` | a literal's text matches the glob |
| `{"textMatches": "(?i)^select"}` | a literal's text matches the regular expression |
| `{"commentIs": "history"}` | a comment is one of the calibrated readings of prose: `history` narrates why the code was CHANGED rather than what it does, `strawman` defends against a mistake nobody was going to make, and `restates` says nothing the code it documents does not. The readings are the prose package's own, measured by its tests in every language, and the set is closed: a rule asks for a judgement that was calibrated, never for prose matching words it writes itself. Asked of a comment (`"select": "comment"`) it judges that comment, and asked of code it judges the comments written for it |
| `{"docReference": {"resolves": false}}` | a doc comment names code that is GONE: a reference it makes (`<see cref>`, `{@see}`, `@see`, Sphinx `:class:`) resolves to nothing, and the project would have had to own it. Two references are left alone, which is the whole difference between this and a broken-link scanner: one the compiler could never have resolved anyway, and one whose owner is declared outside the scan, since nothing here can say whether that owner still has the member. The resolving is the bridge's own |
| `{"commentLike": "*TODO*"}` | a comment on it, or in the run of comments directly above it, matches the glob - and, asked of a comment itself (`"select": "comment"`), what THAT comment says, so one key reads a comment however a rule reached it |
| `{"commentMatches": "(?i)\\b(TODO\|FIXME)\\b"}` | a comment on it, or the comment itself, matches the regular expression, read as commentLike reads one |
| `{"docTag": "deprecated"}` | its documentation carries the tag: `@tag` in PHPDoc and JSDoc, an XML element in C#, a Sphinx field or directive or a Google/NumPy section in a Python docstring |
| `{"documented": false}` | it carries a doc comment, or not with `false` |

**What it refers to**

| Check | Keeps the node when |
|---|---|
| `{"resolves": "App\\Models\\User"}` | what its name refers to, resolved, is that — or, for a value that names nothing (a variable, a property), the class it holds: that exact class, not one extending it |
| `{"resolvesLike": "App\\Models\\*"}` | what its name refers to, resolved, matches the glob, or the class a value holds does: `{"resolvesLike": "*Service", "of": "child:var"}` is a receiver of any *Service |
| `{"targets": "path/filepath.Walk*"}` | the call reaches a declaration whose symbol, as the language's own compiler resolved it, matches the glob, in the scan or outside it: a call named by what it reaches, not by what it is called, so a method of the project's own that happens to share the name never matches. Go, TypeScript and C# carry the target |
| `{"declaredBy": "App\\Models\\*"}` | the member it reads is declared by a class the glob names: the class its receiver holds, followed up its own parents until one declares that member. It is whose field this is, which no name can say, and it is empty — so the step keeps nothing — where the receiver has no type in the scan or no declaration of it declares the member |
| `{"answeredBy": false}` | the type that declares what it reads already names the question it asks — a method of that type whose whole body returns the same thing, read either way round — or does NOT with `false`. It is what tells `use the name that exists` from `give it a name`, and a question asked by the very method that names it is never one of the places asking it |
| `{"sameAs": {"of": "closest:loop", "target": "child:iter"}}` | it names the same thing as another node of the same file, reached by following `of` from it and then `target` from there — the one question a rule could not ask: a `remove` on the very collection its loop walks, an argument that is the field it was just read from. The two must be written the same, else read as the same tree; no node at the end of the path is no pass |
| `{"declaredElsewhere": true}` | what it names is declared in another FILE than the one it sits in, or is not with `false`. A field set from outside the file that declares it has gone around the methods its class wrote for changing itself, which is the question a rule about a type's own business asks; it holds nothing where no declaration in the scan declares what it names, so a rule says the owner too |
| `{"type": "number"}` | the type the expression has, as its language reads it: what a checker gave it, what its shape says, or the type its declaration writes — a primitive among them, where `resolves` answers a class alone |
| `{"typeLike": "Money*"}` | the type the expression has matches the glob |
| `{"calls": {"name": "dd"}}` | a call of its own — not one inside a function nested in it — passes the step |
| `{"argument": {"at": 0, "is": "literal"}}` | the argument at that place passes the step; a negative `at` counts from the end, `-1` the last, and none there passes nothing. With no `at` at all, ANY argument passing the step is enough, which is how a rule asks about one passed by name, whose place is not what identifies it |
| `{"constructs": "Date*"}` | a construction, or on anything else one of its own, creates an instance of a type the glob names — in Python, a call of a class |
| `{"unused": true}` | nothing refers to it: a function or type from outside itself, through the names and calls the scan resolves and its language's call graph; a parameter, by its name read in its function; a field or property, by what reaches its declaration through the type its receiver holds, since a field refers to no symbol in most languages. A member the language itself reaches by name, and one that overrides, are not this: nothing the project writes has to name them |
| `{"referredFrom": {"is": "parameter"}}` | something referring to it — a call, a name, a written type — passes the step. It is `calledFrom` asking about the REFERENCE rather than the file it sits in, which is what tells one side of a thing from another: an event class named by a dispatch and by no listener's parameter is heard by nobody, and `{"not": {"referredFrom": …}}` says exactly that |
| `{"calledFrom": "app/Http/**"}` | something referring to it — a call, most often — sits in a file the glob matches, read as `file` reads one |

**Where it sits**

| Check | Keeps the node when |
|---|---|
| `{"file": "*Repository.php"}` | its path from the project (the repository it lies in, else the folder judged), or any tail of it, matches the glob: `*` stays within a folder and `**` crosses them, as in .gitignore |
| `{"fileMatches": "(^\|/)pages/.*(Create\|Edit)\\.vue$"}` | its path from the project (the repository it lies in, else the folder judged) matches the regular expression, for paths a glob cannot say, such as alternatives |
| `{"namespaceLike": "App\\Http\\*"}` | the namespace, package or module it is declared in matches the glob |
| `{"layer": "App\\Domain"}` | it sits in that layer of the stack the project declares (backend, Python, C#) |
| `{"topLevel": true}` | it sits outside every function, closures too, and every type — code that runs when its file loads |
| `{"withinLoop": true}` | it sits inside a loop, or not with `false` |
| `{"position": "first"}` | it is the `first`, `last` or `only` one of its siblings (an only one is also first and last) |
| `{"field": "cond"}` | it fills that slot of its parent in the tree — the language's own name for the slot, such as `cond`, `body`, `else`, `args` — which is the one axis of place the other checks never ask: a call standing AS a condition rather than one handed as an argument. The names are its language's, so a rule written with one judges that language |
| `{"role": "member"}` | it is that kind of thing, in the words every bridge writes: `statement`, `expression`, `member` (any declaration of a type, a function, a property or an enum case), `type` (a type as written), `pattern`, `markup` or `other`. It is how a rule says `a declaration` without naming a kind per language, which is what a rule about the order or the company of members needs |
| `{"descendant": {"is": "return"}}` | some node inside it passes the step; with `"within": "ownBody"` only the nodes it holds ITSELF are stepped onto, stopping at a function nested in it, which is the difference between a method that saves and one that hands a closure holding the save to a queue |
| `{"child": {"is": "return"}}` | some node one level inside it passes the step, which `descendant` at any depth cannot say: a function whose own body returns, and nothing for one that returns only inside an `if`. With `"field"` only the children filling that field of the tree are stepped onto, as a count's field narrows what it counts |
| `{"body": {"is": "branch"}}` | some statement of its BODY passes the step, read through whatever its language wraps a body in: C# writes a block statement and Python writes bare statements, so `child` answers them differently and this answers them alike. It is how one rule about a loop or a function's body is written once for every language |
| `{"inside": {"is": "catch"}}` | some node above it, up to the file's root, passes the step |
| `{"documents": {"is": "type-declaration"}}` | asked of a comment (`"select": "comment"`), the declaration the comment is written for passes the step: the node it sits above, or the node whose line it ends. A comment that documents nothing passes no step, so a rule can find the comments that are written about something and the ones that are written about nothing |
| `{"all": [{"name": "store"}, {"hasModifier": "public"}]}` | it passes every step listed, as one node: a public `store`, where a step alone makes one check |
| `{"any": [{"is": "loop"}, {"is": "branch"}]}` | it passes one of the steps listed, so a rule says `either of these` without a second rule |
| `{"not": {"hasModifier": "final"}}` | it does NOT pass the step, so a `where` can hold what only a `reject` could say |
| `{"rule": "ActionClass"}` | the rule of that name finds it too, so one rule is built from another instead of repeating its checks |
| `{"next": {"is": "return"}}` | the sibling right after it passes the step |
| `{"previous": {"is": "branch"}}` | the sibling right before it passes the step |
| `{"unnamedCondition": true}` | it is a logical chain standing for a concept nobody named: three parts or more asking something of two or more different OBJECTS, where a branch or a loop decides by it. It counts the objects and never the parts, since three tests of one subject are a range check; a chain a function returns is already the name that was asked for, and a guard that leaves the body says what it means by leaving |
| `{"bindsItsOwnTest": true}` | the branch binds a name in its header for its own body to read: Go's `if v, ok := m[k]; ok`, C#'s `is T v` and `out var`, Python's `:=`. It is the exemption a rule about conditions needs: such a test cannot move behind a name without widening what the branch alone was entitled to read |
| `{"redundantElse": true}` | its `else` stands though the taken path already left, so the else's statements can sit after it at the enclosing level; a branch whose else is the next rung of a ladder is not this. Every language answers it, from the neutral kinds and its own grammar |
| `{"soleLoopBodyGuard": true}` | it is the WHOLE body of a loop, burying two statements or more behind its condition where a `continue` past what does not apply would keep them flat. A body that ends by leaving the loop is picking an item, and a branch that binds a name for its own body is pardoned, since inverting it would widen that name |
| `{"ladderRungs": {"atLeast": 3}}` | its ladder has that many rungs AND every one compares the same subject with a constant, which is the shape whose answer is a dispatch on that subject or a lookup keyed by it. Nothing for a ladder of unrelated tests, and nothing for a branch that is itself a rung, so one ladder is counted once |
| `{"passesItsElementOn": true}` | a loop hands its item on to something else, every use of it; a loop whose item reaches nothing, and one that only hands the item to the method it stands in, answer no. It is the pardon the walk-the-collection rules share |
| `{"nestedAtLeast": {"is": "loop", "count": 3}}` | it and the nodes above it that pass the step number at least `count` |

**Whether it is ours to judge**

| Check | Keeps the node when |
|---|---|
| `{"testCode": true}` | it is test code, as its bridge marks it or its language names test files |
| `{"overrides": true}` | a function is a member its type owes a supertype: its language's compiler says so, or one of the same name is declared by a type it extends or a contract it honours, in the scan or outside it. It is the exemption every rule about a signature needs, since the signature is not the overriding method's to change — and it is what a name check gets wrong, where a method merely SHARING a name with another class's is nobody's to answer for |
| `{"implicit": true}` | the language itself calls the declaration or binds the parameter: a constructor, a magic or dunder method, Python's `self` and `cls`. Nothing the project writes calls it by that name, so a rule about how a thing is called, named or reached has nothing to say about it |

**How big it is**

| Check | Keeps the node when |
|---|---|
| `{"parameters": {"atLeast": 5}}` | a function declares that many parameters — a variadic, a defaulted one and Python's `self` count once each |
| `{"arguments": {"atLeast": 4}}` | a call or construction is handed that many arguments — a named or a spread one counts once |
| `{"lines": {"atLeast": 40}}` | it spans that many lines, its first and last among them |
| `{"members": {"is": "function", "atLeast": 20}}` | a type declares that many members directly that pass the step (every member, with no check); with `"inherited": true`, the members its parents and traits declare in the scan count too |
| `{"complexity": {"atLeast": 10}}` | one plus every branch, loop and catch in it, as its language marks them, is that many — a switch is one branch, `&&` and `\|\|` are not counted, and a function inside it is its own count |
| `{"count": {"descendant": {"is": "return"}, "atLeast": 4}}` | that many of its descendants pass the step — or `"child"`, with `"field"` to count one field's children; with `"distinct": "child:var"` (any target `of` takes), the counted nodes whose target is written the same count once: `atMost: 1` is one receiver |
| `{"ancestors": {"atMost": 2, "within": "App\\*"}}` | its class's own line of parents runs that long, followed through the scan and the declarations outside it; `within` counts only the parents a glob names, so a project bounds the levels of its OWN and says nothing about the framework class underneath them |
| `{"duplicated": {"atLeast": 2}}` | that many functions of the codebase, it among them, have its body, read from the tree, blind to spacing and comments |
| `{"callers": {"atLeast": 2}}` | that many calls of the codebase reach the declaration, each resolved through the tree: `atMost: 0` is a declaration nothing calls |
| `{"repeated": {"atLeast": 3, "files": 2}}` | that many PLACES of the codebase hold a node reading as this one does, spread over at least `files` of them (one when it says none). A place is the function the node sits in, or, where no function does, the type declaration it sits in, so a value a class declares about itself - the event a listener says it hears - is a place and two classes declaring one event are two, read from the tree as `duplicated` reads a body: blind to what a local is called and which datum a literal holds. `"exact": true` keeps both, which a repeated string literal needs; `"eitherWay": true` reads a question and its negation as one (`==` with `!=`, `is` with `is not`, `in` with `not in`); `"by": "owner"` groups by the type that declares what the left side reads, so one question about the fields of two classes that share a name is two. Anything is counted, not only a comparison: a member access of a chain counts the reach `event.data.part(X).tasks` written again and again |

**Its type**

| Check | Keeps the node when |
|---|---|
| `{"typeKind": "interface"}` | a type declaration declares a `class`, `interface`, `enum`, `trait`, `record`, `struct` or `protocol` |
| `{"extends": "Controller"}` | its class extends the type directly. Its class is the one a type declaration declares, else the one the node names (`X`, `X::class`), constructs (`new X`) or holds a value of (`$this->service`, a typed parameter) |
| `{"extendsAny": "Exception"}` | the type is anywhere in its class's chain of parents, followed through the scan and the declarations outside it the language knows; a glob (`*Service`, `[A-Z]*Repository`) matches a parent's whole name or its last part |
| `{"implements": "ShouldQueue"}` | its class honours the contract: its own, its parents', and the contracts those extend |
| `{"uses": "AsAction"}` | its class uses the trait: itself, through a parent, or through a trait it uses |
| `{"isA": "Illuminate\\Http\\Request"}` | its class is the type, extends it or honours it, as PHP's `instanceof` reads it; a glob matches any of them |
| `{"hasAnnotation": "Route"}` | a declaration carries the attribute or decorator |
| `{"hasAttribute": "data-dusk"}` | a template element writes the attribute, plain or bound: `data-dusk` or `:data-dusk` |
| `{"sibling": "{folder}.php"}` | a file the scan read matches the pattern, read from its file's folder: `{folder}.php` beside it, `../{folder}.php` one folder up, `{folder}` standing for the name of that folder |
| `{"value": true}` | it is a literal of that very value, kind and all: the decoded value its language folded, read as the rule wrote it. `{"value": true}` is a bare `true` and NOT the string "true" nor the number 1, `{"value": 0}` is the number zero read as a number, so `0` and `0.0` are one value, and `{"value": null}` is the literal null. It is what a rule about a flag handed to a call needs, where `{"is": "literal"}` cannot tell a boolean from a label and `{"text": "true"}` matches a string that merely says so |
| `{"nullable": true}` | its type admits absence, however its language spells it: `?Money`, `Money\|null`, `str \| None`, `string?`, `number \| undefined`. It reads the TYPE, so a union of two real types is not it and neither is a type merely named like one (`Maybe<T>`), which a glob over the text cannot tell apart. Asked of an expression it is the type the value holds, of a declaration the type it writes, and of a type node the type of the declaration it is written on; a node whose type the engine cannot read keeps nothing either way, since nothing known about a type says nothing about absence |
| `{"typeShape": "tuple"}` | its type has that shape, read from the type itself: `named`, `keyword`, `parameter`, `union`, `intersection`, `array`, `tuple`, `object`, `function`, `literal`, `pointer`, `map` or `opaque`. It is how a rule says `this answers a positional tuple` or `this hands back a bare array` without reading a type's text, and `opaque` is the honest answer for a type its bridge does not model |
| `{"typeOrigin": "docblock"}` | its type came from there: `written` (the source's own annotation), `docblock`, `attribute`, `inferred` (the engine's own sound inference) or `compiler` (Roslyn, mypy, tsc). It is two rules nothing else can say: a type a docblock carries where the signature carries none, and the exemption `do not judge a type nobody wrote` — a reject on `inferred` leaves the engine's own reading alone |
| `{"elementType": "App\\Models\\*"}` | its type is a collection OF that type, read from a generic argument, a docblock `list<X>`, or an attribute such as `#[DataCollectionOf(X::class)]`. A collection whose element nothing says keeps nothing, since a bag of unknowns is not a bag of the type asked about |
| `{"returnType": "?*"}` | a function's written return type matches the pattern, spaces dropped, or the type it names resolves to; it reads the text, so `*\|null` finds `int\|null` — for absence itself ask `nullable` of the type, which every language answers the same way |
| `{"parameterType": "array"}` | a parameter's written type matches the pattern, read as returnType reads one |

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
| `{"php": "divergentTwin"}` | it is one of two methods doing one job where one does less of it: a step the other takes and it forgot |
| `{"php": "facadeCall"}` | it is a static call on a Laravel facade |
| `{"php": "inDataClass"}` | it sits in a Spatie Data class |
| `{"php": "inNamedConstructor"}` | the function around it builds an instance of its own class |
| `{"php": "inPageObject"}` | it sits in a Data class that is a page object |
| `{"php": "returnedValue"}` | it is the value a return statement returns |
| `{"php": "typeNarrowingGuard"}` | it is an outermost `&&` of two or more instanceof checks |
| `{"python": "constructor"}` | it is a class's __init__ |
| `{"python": "divergentTwin"}` | it is one of two defs doing one job where one does less of it: a step the other takes and it forgot |
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
| `{"vue": "component"}` | the element is a component, as Vue's compiler resolves its tag: `<Foo>`, `<my-widget>`, `<component :is>`, `<transition>` |
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
| `annotation:<name>` | the annotation of that name the declaration carries: the attribute, the decorator. `hasAnnotation` says one is THERE, where this asks what it was handed — `{"of": "annotation:Route", "argument": {"at": 0, "textLike": "/admin*"}}` is a route under /admin. No node where the declaration carries no annotation of that name |
| `returnType` | the type NODE a function writes as its return, so everything a rule asks of a type composes on it: `{"of": "returnType", "isA": "Collection"}` is a function answering a collection, where globbing the type's text could only match its spelling. No node for a function that writes none, which is no pass but no answer |
| `parameterType` | the type NODE a parameter writes, read as `returnType` reads a function's |
| `condition` | the question it decides by: the test of an `if`, a `while` or a conditional expression, so `{"of": "condition", "is": "call"}` is a branch deciding by what a call answers. No node for what decides nothing, a `for` over a collection among them, which walks what it is given |
| `subject` | what a comparison tests against a constant: the side that is no constant, when the other one is. It is what a ladder over ONE subject is told by, and it answers nothing where neither side is a constant or both are, since two values compared test no subject and two constants decide nothing about one |
| `receiver` | the object it is sent to: the `$order` of `$order->ship()` and the `order` of `order.ship()`, whether the step is asked of the read or of the call around it, so `{"of": "receiver", "resolvesLike": "*Repository"}` is anything sent to a repository. No node for a call of a bare name, which is sent to nobody |
| `declaration` | the declaration of the member it reaches: the method a call runs, the field an access reads, the field a named argument sets. The resolver's own answer where its bridge followed the call, and the declaring type's own members otherwise, so `{"of": "declaration", "hasModifier": "static"}` is a call of a static method wherever it was written |
| `declaredBy` | the declaration of the type that declares the member it reads, so every check composes on whose field it is: `{"of": "declaredBy", "file": "app/Models/**"}` is a field of a model, read from anywhere |
<!-- END: rule-checks -->

**A list of words, named once.** Several rules often check against the same words, and editing that list in four
files is how the fifth word ends up in three of them. Declare it once in a `lists.json` under
`.commandments/custom/` — the one file name there that is not a rule, read wherever it sits, so the lists may live
beside the rules that check against them or in a folder of their own, and a rule names a LIST and never the file it
is in — and name it where the words would go:

```json
{ "poetry": ["ephemeral", "sublime", "liminal"] }
```

```json
{"nameIn": ["$poetry", "plain"]}            // the whole list among a check's words
{"nameMatches": "^(${poetry})$"}            // and inside a pattern, each word quoted so a dot is a dot
{"nameMatches": "(?i)^(${poetry})$"}        // the same words, any case, which is the regexp's own business
```

A rule is written out before it is read, so every check that takes words or a pattern takes a list without knowing
lists exist. A rule naming a list the project has not declared is refused by name, with the lists it does have,
rather than running against nothing and passing everything.

A **type name** — in `extends`, `extendsAny`, `implements` and `hasAnnotation` — is matched by the symbol it
resolves to when it is qualified (`App\Http\Controller`, `shop.models.Base`, `N.IOther`), and by its last
part when it is bare (`Controller`). A type the scan cannot resolve is matched by what it says. In C#,
`Serializable` also names `SerializableAttribute`. Python writes its contracts among its bases, so
`implements` there reads the bases that are a `Protocol` or an abstract base class — inheriting `abc.ABC`
or made by `ABCMeta` — and a plain base is only `extends`. A **type
pattern** has one wildcard, `*`: a `?` is itself, so `?*` is PHP's `?int`. It reads the type as written,
so a type spelt as a union is matched as one — `*|null`, `* | None` — and a function or parameter with no
type written matches no pattern: reject `"*"` to find the untyped ones.

`unused` never flags what the language itself calls or binds — a constructor, a magic or dunder method,
Python's `self` — nor a member a supertype dictates, nor a parameter of a declaration with no body. It
knows only the code it scans, though: a controller action a route calls, a listener the framework
dispatches, or a function another project imports looks unused, so pair it with a `reject` for them
(`{"hasAnnotation": ...}`, `{"extendsAny": ...}`, `{"file": ...}`).

**Paths** — `file`, `calledFrom` and `testCode` — are read from the folder the scan was pointed at, its
own name included (`src/Cart.php` for `judge src`), never from where the project sits on the disk.

**Test code** is a file C#'s bridge marks as a test project's, and elsewhere a file the language's test
runner collects: PHP's `*Test.php`, Python's `test_*.py`, `*_test.py` and `conftest.py`, a Vue
`*.test.*` or `*.spec.*`, and in every language a file under a `tests` or `test` folder (`__tests__`
too, in Vue) of the tree judged. C# deprecation is the `[Obsolete]` attribute, which `hasAnnotation` finds, and a
Python docstring is no comment, so `commentLike` does not read one: `docTag` does.

Every size takes `atLeast`, `atMost` or both, and both are inclusive.

A node's **siblings** fill the same field of the same parent: a statement's are the other statements
of its block. `nestedAtLeast` counts within one function: a closure starts the count again, unless
functions are what it counts, and an else-if continues its if rather than nesting in it, however its
language's tree holds one.

A glob matches the whole name: `*` is any run of characters, `?` one, and a backslash is itself, so a
PHP class is written as it reads. A regular expression is Go's (RE2) and matches anywhere unless it is
anchored. A `layer` step reads the layers the config declares for the engine's
`NamespaceDependencyDetector` (`commandments layers` proposes them), so a rule can speak of a layer
without repeating its namespaces; with none declared it finds nothing.

A rule the binary cannot run says why when `judge` starts — the file, and which part is wrong.

## Scaffold it — don't hand-write the plumbing

```bash
vendor/bin/commandments make NoRawSql                    # a PHP rule and a skill of its own
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

## Let one rule into a folder the project excludes

An excluded folder is excluded for every rule, which is usually right and sometimes exactly wrong: a project
excludes `tests` because the rules about naming read a test's name as a sentence, which is what a test name is,
and one project measured 9,773 findings in 85 files that way. But a rule written ABOUT tests belongs there.

**Write it in the rule's own file.** The folders a rule of yours judges belong with the rule, in the file you
wrote and no install rewrites:

```json
{
    "engine": "python",
    "judges": ["tests"],
    "sin": {"name": "tests-drive-the-real-path", "skill": "tests-drive-the-real-path"},
    "find": { "...": "..." }
}
```

A shipped rule carries no such list, because you could not change one inside a rule the package ships. Name its
folders in the config instead, under that one rule, beside its other `configure` steps:

```json
"configure": {"php/FacadeCallDetector": [{"judges": ["tests"]}]}
```

The two are read as one answer, so a folder named in both counts once, and `judges` is the config's own step
rather than a method of the detector, which is why it reads the same under a rule of yours (keyed by its bare
name) as under a shipped one (keyed `engine/Name`).

The folder stays excluded for every other rule. An edit inside it raises that rule's marks live, as any other
edit does, instead of waiting for somebody to run a judge over the folder by hand. A run GIVEN a path is never
widened by it: a whitelist says which rule may look inside an excluded folder, not that a run should reach past
what it was asked for. A folder you merely left out of `paths`, rather than naming under `exclude`, is reached
the same way.

They are named as yours wherever they fire: `judge` prints `[NoRawSqlDetector (custom)]` in the
console and in `sins.md`, `judge --list` lists them so, and `report --detector` refuses them — nothing
upstream can fix a rule the package does not ship.

## The anatomy — a rule, a sin, a skill

**The skill teaches.** `skills/<slug>/SKILL.md` is what a finding sends the reader to, published as you
write it into the project's skill library as `commandments-<slug>`. Its front matter says when to load
it (`description`), what the briefing lists (`summary`), how often it comes up (`tier: mandatory` or
`keep-in-mind`) and which languages it teaches (`languages: [php]`).

**The sin names.** Inside the rule: a `name` (the `--sin=` id), the `skill` it points at by slug — your
own, or a shipped one such as `php/absence` — the `description` (the symptom) and the `rule` (the
positive directive the fix follows).

**The query finds.** Nothing else. It has no fix logic, and it knows nothing about the report.

## Classify by what the code IS, never by what it is CALLED

The cardinal rule. Derive the answer from what a node is and what its name resolves to — the neutral
kind, the language's kind, the class a call reaches. **Never** from a class, method or variable name, a
suffix, or a hardcoded list. The name, text and `file` checks exist because a call has to be named at some
point; reach for them last, and never as a list of the exceptions you happened to meet. Names lie, and a
rule built on one fires on the wrong code the first time somebody renames well.

## Ask about the TYPE, not the name it was reached by

A name tells you what a reader typed; a type tells you what the value IS. The difference shows the moment
someone copies the value into a variable:

```json
{
    "select": "member-access",
    "where": [
        { "nameMatches": "^_" },
        { "of": "receiver", "typeLike": "*ShellLine" }
    ],
    "reject": [{ "of": "receiver", "is": "self-reference" }]
}
```

That finds `line._source_line` AND `copied = line; copied._source_line`, because the receiver's type follows
the copy. Written with `name` and `descendant` instead, naming the variable the value usually sits in, the
second one walks straight past. A rule that misses the shape somebody reached for by accident is not
protecting anything.

Two things to know before you lean on it:

- **`type` and `typeLike` are the ones to use.** `resolves` and `resolvesLike` answer a CLASS a name refers
  to, which the PHP engine fills; in Python they answer nothing at all, so a rule built on them there finds
  nothing and looks broken. `isA` reads PHP's `instanceof` and answers nothing in Python either.
- **Reject the owner reading its own state**, as above. Without that, the rule fires inside the class whose
  field it is, which is the one place the read is nobody else's business.

## One rule, every language it fits

`engine` takes a name or a LIST of them, so a rule that asks only what every language answers is written
once instead of once per engine:

```json
{
    "engine": ["python", "go", "csharp"],
    "sin": { "name": "loop-wrapped-in-if", "description": "…", "skill": "…" },
    "find": { "select": "loop", "where": [{ "body": { "is": "branch" } }] }
}
```

Two things make that work, and both are easy to get wrong.

**Use the neutral words.** `loop`, `branch`, `call`, `assignment`, `comparison`, `member-access`, `literal`,
`import`, `catch`, `function`, `type-declaration` and the rest mean the same in every language, because each
language maps its own nodes onto them. A `kind:` or an `operator` is one language's own word, so a step naming
one simply never matches in the others — allowed, and sometimes what you want, but then say so to yourself.

**Use `body`, not `child`, for what a construct contains.** A loop's body is a block statement in C# and bare
statements in Python, so `child` finds your branch in one language and not the other, while `body` reads
through whatever each language wraps it in. This is the difference that makes a neutral rule look like two
rules.

Where the languages genuinely differ, a step carries the per-language check in the same file — `{"php":
"facadeCall"}` — answered only by that language. And `commandments rule migrate` merges rules you already have
that differ in nothing but the engine they judge; it leaves alone any pair that differs in more, because then
they are two rules and merging them would change what one of them finds.

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

A rule whose finding is about a file itself, such as one selecting `kind: SourceFile` or narrowing by
`file`/`fileMatches` alone, reports on the file's first line, which no leading comment can mark. Mark that
sample with `@sin-file NoFormOutsideFormsDetector` anywhere in the file (`@righteous-file` and `@fixed-file`
likewise): it marks the whole file, wherever the comment stands.

**A comment marks the LINE below it, not the declaration it sits above.** This is the one trap that costs an
afternoon, because it reads as something else entirely. If the rule flags a member read inside a method, the
mark goes directly above THAT line:

```python
def through(tool: Tool) -> str:
    copied = tool.shell

    # @sin NoWholeLineRead
    return copied._source_line
```

Put the mark above the `def` instead and both halves of one mistake are reported as if they were two faults:
the mark is missed where it stands, and the finding is unmarked where it is. `rule prove` now says so when it
sees both in one file, but the mistake is easy and the reason is worth knowing. PHP is where the habit comes
from: there a marker is an ATTRIBUTE on a declaration, `#[Sinful(X::class)]`, and it covers anything the
detector flags inside it. Python, C#, Vue and Go mark a line.

**A sample is judged by your other rules too.** It sits in the project, so a sample written to prove one rule
can be flagged by another, and then `rule prove` fails that other rule on code you wrote as a fixture. Write
samples that would pass review, or mark them `@righteous` for the rules they trip.

```bash
vendor/bin/commandments rule prove
```

holds every rule to its marks: it fails a rule that misses a `@sin`, flags a `@righteous` twin or a
`@fixed` fix, or flags anything unmarked. The samples stay — they are the rule's spec, and `rule prove`
runs again whenever the rule changes. A rule no sample marks with `@sin` fails too — a rule shown to flag
nothing has been shown nothing — and so does a mark naming no rule. A check that reads across files —
`unused`, `calledFrom`, `duplicated`, `layer` — sees only the samples there, so prove it on samples that
hold both ends, and calibrate it on the real tree. The near-misses are the whole point: a rule that fires on
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

## Auto-fix it only when the fix is mechanical

A rule reports by default. If the honest fix depends on what the code MEANS — which value object to
introduce, where absence really belongs — an auto-fix would launder the problem instead of solving it.
Leave `fix` out and let the skill teach the reader.

When the fix follows from the found node alone, declare it under `fix`, and `repent` applies it to
every finding (PHP, Vue and TypeScript rules). Name exactly one of:

- `replace` — the text written in place of the found node. Each `{target}` stands for the source of
  the node an `"of"` target names, hops joined by `/`:

  ```json
  "fix": { "replace": "{child:left} > 0 && {child:right}" }
  ```

  turns `items.length && <List />` into `items.length > 0 && <List />`, and
  `{ "replace": "{child:arguments/child:body}" }` unwraps `useMemo(() => total * rate, [total, rate])`
  to `total * rate`. A finding whose placeholder reaches no node is left for a human.
- `substitute` — Go regular-expression substitutions applied in order to the found node's source,
  `${1}` standing for a group:

  ```json
  "fix": { "substitute": [{ "matches": "(^|[\\s:\"'`])ml-", "with": "${1}ms-" }] }
  ```

Narrow the rule's `where` until every node it finds takes the fix: a `replace` that unwraps a callback
belongs on a rule that admits only an expression body.

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
| `commandments make <Name>` | scaffold a PHP commandment and turn it on |
| `commandments make <Name> --engine=vue` | scaffold a Vue one instead |
| `commandments make <Name> --engine=typescript` | scaffold a TypeScript one instead |
| `commandments make <Name> --engine=python` | scaffold a Python one instead |
| `commandments make <Name> --engine=csharp` | scaffold a C# one instead |
| `commandments make <Name> --skill=NAME` | point the sin at an EXISTING skill (shipped or your own) instead of writing a new one |
| `commandments make <Name> --from=<template>` | start from a ready rule to adapt: max-function-length, max-nesting, max-parameters, no-debug-calls, no-sql-in-controllers, no-todo-comments |
| `commandments rule explain <file> [--line=N]` | print the tree a rule reads in the file: every node's line, kind, neutral kinds, field, name and what it resolves to |
| `commandments rule try <Rule> <path>…` | run one rule over the paths without turning it on, and print each match with its line: a folder, or the files a change touched |
| `commandments rule prove [path]` | check every rule of the project flags exactly the code its samples mark, and nothing else |
| `commandments rule schema` | print the JSON Schema of a rule file |
| `commandments rule migrate [--dry-run]` | bring your own rules up to this release: merge the copies that differ only in the engine they judge into one rule naming them all, and name every rule this release no longer reads, with the reason |

<!-- END: commands:make,rule -->

## Rules that work, one per kind of question

[`reference/examples.md`](reference/examples.md) holds a working rule for each shape of question: by what a
node IS, by the type a value HAS, one rule for several languages, by where the code sits, by how much there is
of it, by what only the resolver knows, and one rule built on another. Every one of them is parsed by a test,
so none of them can rot into a shape the tool no longer runs.
