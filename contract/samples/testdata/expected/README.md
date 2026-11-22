# Expected trees

Each folder is one construct. It holds a small source file per language the construct applies to, and the version 5
tree that source must translate to (`<name>.jsonl`: header, one file line, trailer). The trees are the specification
the six bridges are proven against: `contract/samples/compare_test.go` (`TestEveryBridgeWritesTheExpectedTree`) runs
each bridge on each source and compares what it writes with the tree. `contract/samples_test.go` checks every tree
validates against `contract/tree.schema.json` and that every span points into its source.

## How a tree is written

- **Spans** are byte ranges into the source, with the line the node starts on. A statement's span includes its `;`; an
  expression's does not. A braced block runs from `{` to its `}`. A Python block runs from its first statement to the
  end of its last. A function or type runs from its first keyword or modifier to the end of its body.
- **An assignment written as a statement** is an `expression-statement` holding the `assignment`, in every language,
  Python and Go included: the same operation has the same tree.
- **Names** are written without a sigil: PHP's `$amount` is a `binding` or `reference` named `amount`, its span the
  `$amount` written.
- **A Vue file** has one module per `<script>`, spanning the script's content, and a module named `template` whose
  statements are the template's top-level elements.
- **A Python module** is named by its folder and file as the bridge reads them: `return_before_else.python` for
  `return-before-else/python.py`, since the comparator places each source in a folder named for its construct with its
  dashes as underscores.
- **`async`** is the fact, never a modifier. `modifiers` holds only the keywords written for visibility and the like.

## How a bridge's tree is compared

`Differences` in `contract/samples/compare.go` walks the two trees side by side and ignores local ids:

- every kind, every role and the number of nodes each role holds, and every span, must match;
- every parser fact must match both ways, except `symbol`, `effective` and `written`, which name rather than shape;
- those three, and every compiler, index and package fact (`refers`, `target`, `resolvedType`, `constantValue`,
  `obligations`, `exhaustive`, `namedResults`, `resolves`), are compared only where the expected tree states them;
- a fact written with its documented default counts as absent: `passing: value`, `collects: none`, `position: either`,
  `spread: none`, `expansion: none`, `evaluated: call`;
- links are compared where the expected tree states them, by the nodes the walk pairs;
- comments are compared by form, text, span, trailing and attachment, and a fragment where the tree states one;
- the file's error count, test fact, module and whether its resolver ran must match; a type's `origin` wording is ignored.

## The constructs

| Construct | Doc 61 story | Languages | Not here, and why |
|---|---|---|---|
| `return-before-else` | 1 | php, python, csharp, typescript, go | vue: its script is TypeScript |
| `elif` | 2 | python, php, typescript, csharp, go | vue: its script is TypeScript |
| `filtered-catch` | 3 | csharp | only C# filters a catch (`when`) |
| `return-in-callback` | 4 | typescript, php, csharp, go | python: a lambda holds one expression and cannot return; vue: TypeScript |
| `reversed-error-check` | 5 | go | an error value checked against `nil` is Go's; other languages throw |
| `vue-if` | 6 | vue | only a Vue file has a template beside its script |
| `pass-and-comment` | 7 | python | `pass` is Python's; an empty body elsewhere is shown by `filtered-empty-catch` and `test-catch-resolved` |
| `two-results` | 8 | go | only Go returns several results |
| `promoted-parameter` | 9 | php, typescript | csharp: a primary constructor's parameters are captured, not fields; python and go have none |
| `partial-class` | 10 | csharp | only C# splits a class across declarations |
| `overloads` | 11 | typescript | php, python and go have no overloads; C#'s are separate methods, shown in `documented-overload` |
| `shadowed-exception` | 12 | python | the story is Python's builtins; other languages' exceptions are imported names |
| `pointer-receiver` | 13 | go | only Go declares a method's receiver as a pointer |
| `aliased-import` | 14 | php, python, typescript, go, csharp | vue: its script is TypeScript |
| `null-or-false` | 15 | typescript, php | python and go have no null-coalescing operator; C#'s `??` is the same fallback as TypeScript's |
| `empty-string-key` | 16 | python, php, typescript | csharp and go have no map literal keyed by an arbitrary string in the same shape |
| `non-ascii-before-script` | 17 | vue | only a Vue file has text in another block before its script |
| `documented-overload` | 18 | csharp | the story is C#'s `///` on an overload |
| `prose-and-docstring` | 19 | python | only Python's documentation is a docstring in the body |
| `recovery-node` | 23 | typescript, php, csharp, go | python: its parser does not recover, so the whole file fails (`parse-failure`) |
| `bare-except-pass` | 25 | python | a bare `except:` is Python's |
| `specific-except-empty` | 26 | python | the story is Python's |
| `filtered-empty-catch` | 27 | csharp | only C# filters a catch |
| `test-catch-resolved` | 28 | php | the story is PHPUnit's |
| `test-catch-unresolved` | 29 | php | the story is a PHP test's; Python's `assert` has no kind yet and is translated as `unsupported` |
| `handler-single-exit` | 30 | php | the story is PHP's |
| `go-error-guards` | 31 | go | the story is Go's error guard |
| `log-then-fallback` | 32 | python, typescript | the story holds in every language with a handler; these two show it |
| `pipe-call` | 33 | php | only PHP writes a pipe `value \|> callable`, which is the call `callable(value)` |
| `selectors` | section 19, selectors | php, python, csharp, typescript, go, vue | see below |
| `flags` | section 19, flags | php, python, csharp, typescript, go, vue | see below |
| `parse-failure` | unknown evidence | python | a file no parser could read, so mypy checks nothing (its resolver did not run) |
| `unresolved-symbol` | unknown evidence, story 54 | typescript | a call of a name nothing declares, with the resolver run |
| `contract-csharp-collections` | doc 65, the bridge validator | csharp | written for the csharp bridge's contract score: the items it proves are named in reports 119 to 121 |
| `contract-csharp-declarations` | doc 65, the bridge validator | csharp | written for the csharp bridge's contract score: the items it proves are named in reports 119 to 121 |
| `contract-csharp-patterns` | doc 65, the bridge validator | csharp | written for the csharp bridge's contract score: the items it proves are named in reports 119 to 121 |
| `contract-csharp-statements` | doc 65, the bridge validator | csharp | written for the csharp bridge's contract score: the items it proves are named in reports 119 to 121 |
| `contract-csharp-top-level` | doc 65, the bridge validator | csharp | written for the csharp bridge's contract score: the items it proves are named in reports 119 to 121 |
| `contract-csharp-unsupported` | doc 65, the bridge validator | csharp | written for the csharp bridge's contract score: the items it proves are named in reports 119 to 121 |
| `contract-go-declarations` | doc 65, the bridge validator | go | written for the go bridge's contract score: the items it proves are named in reports 119 to 121 |
| `contract-go-flow` | doc 65, the bridge validator | go | written for the go bridge's contract score: the items it proves are named in reports 119 to 121 |
| `contract-go-switches` | doc 65, the bridge validator | go | written for the go bridge's contract score: the items it proves are named in reports 119 to 121 |
| `contract-php-declarations` | doc 65, the bridge validator | php | written for the php bridge's contract score: the items it proves are named in reports 119 to 121 |
| `contract-php-dispatch` | doc 65, the bridge validator | php | written for the php bridge's contract score: the items it proves are named in reports 119 to 121 |
| `contract-php-enum` | doc 65, the bridge validator | php | written for the php bridge's contract score: the items it proves are named in reports 119 to 121 |
| `contract-php-expressions` | doc 65, the bridge validator | php | written for the php bridge's contract score: the items it proves are named in reports 119 to 121 |
| `contract-php-flow` | doc 65, the bridge validator | php | written for the php bridge's contract score: the items it proves are named in reports 119 to 121 |
| `contract-php-hooks` | doc 65, the bridge validator | php | written for the php bridge's contract score: the items it proves are named in reports 119 to 121 |
| `contract-php-jumps` | doc 65, the bridge validator | php | written for the php bridge's contract score: the items it proves are named in reports 119 to 121 |
| `contract-php-scopes` | doc 65, the bridge validator | php | written for the php bridge's contract score: the items it proves are named in reports 119 to 121 |
| `contract-php-top-level` | doc 65, the bridge validator | php | written for the php bridge's contract score: the items it proves are named in reports 119 to 121 |
| `contract-python-comprehensions` | doc 65, the bridge validator | python | written for the python bridge's contract score: the items it proves are named in reports 119 to 121 |
| `contract-python-decorators` | doc 65, the bridge validator | python | written for the python bridge's contract score: the items it proves are named in reports 119 to 121 |
| `contract-python-expressions` | doc 65, the bridge validator | python | written for the python bridge's contract score: the items it proves are named in reports 119 to 121 |
| `contract-python-match` | doc 65, the bridge validator | python | written for the python bridge's contract score: the items it proves are named in reports 119 to 121 |
| `contract-python-references` | doc 65, the bridge validator | python | written for the python bridge's contract score: the items it proves are named in reports 119 to 121 |
| `contract-python-scopes` | doc 65, the bridge validator | python | written for the python bridge's contract score: the items it proves are named in reports 119 to 121 |
| `contract-python-unsupported` | doc 65, the bridge validator | python | written for the python bridge's contract score: the items it proves are named in reports 119 to 121 |
| `contract-python-typing` | doc 65, the bridge validator | python | written for the python bridge's contract score: enums and their cases, a type alias, type parameters, isinstance as a type test, typing.cast |
| `contract-typescript-constants` | doc 65, the bridge validator | typescript | written for round three of the contract score: folded constants, exhaustive matches, type parameter constraints and the last roles of its bridge |
| `contract-vue-script-constants` | doc 65, the bridge validator | vue | written for round three of the contract score: folded constants, exhaustive matches, type parameter constraints and the last roles of its bridge |
| `contract-csharp-generics` | doc 65, the bridge validator | csharp | written for round three of the contract score: folded constants, exhaustive matches, type parameter constraints and the last roles of its bridge |
| `contract-go-generic-call` | doc 65, the bridge validator | go | written for round three of the contract score: folded constants, exhaustive matches, type parameter constraints and the last roles of its bridge |
| `contract-php-attributes` | doc 65, the bridge validator | php | written for round three of the contract score: folded constants, exhaustive matches, type parameter constraints and the last roles of its bridge |
| `contract-python-constants` | doc 65, the bridge validator | python | written for round three of the contract score: folded constants, exhaustive matches, type parameter constraints and the last roles of its bridge |
| `contract-typescript-async-generator` | doc 65, the bridge validator | typescript | written for the typescript bridge's contract score: the items it proves are named in reports 119 to 121 |
| `contract-typescript-compiler-facts` | doc 65, the bridge validator | typescript | written for the typescript bridge's contract score: the items it proves are named in reports 119 to 121 |
| `contract-typescript-declarations` | doc 65, the bridge validator | typescript | written for the typescript bridge's contract score: the items it proves are named in reports 119 to 121 |
| `contract-typescript-expressions` | doc 65, the bridge validator | typescript | written for the typescript bridge's contract score: the items it proves are named in reports 119 to 121 |
| `contract-typescript-imports` | doc 65, the bridge validator | typescript | written for the typescript bridge's contract score: the items it proves are named in reports 119 to 121 |
| `contract-typescript-labels-loops` | doc 65, the bridge validator | typescript | written for the typescript bridge's contract score: the items it proves are named in reports 119 to 121 |
| `contract-typescript-switch-dispatch` | doc 65, the bridge validator | typescript | written for the typescript bridge's contract score: the items it proves are named in reports 119 to 121 |
| `contract-typescript-try-finally` | doc 65, the bridge validator | typescript | written for the typescript bridge's contract score: the items it proves are named in reports 119 to 121 |
| `contract-vue-recovery` | doc 65, the bridge validator | vue | written for the vue bridge's contract score: the items it proves are named in reports 119 to 121 |
| `contract-vue-script-declarations` | doc 65, the bridge validator | vue | written for the vue bridge's contract score: the items it proves are named in reports 119 to 121 |
| `contract-vue-script-expressions` | doc 65, the bridge validator | vue | written for the vue bridge's contract score: the items it proves are named in reports 119 to 121 |
| `contract-vue-script-statements` | doc 65, the bridge validator | vue | written for the vue bridge's contract score: the items it proves are named in reports 119 to 121 |
| `contract-vue-template-markup` | doc 65, the bridge validator | vue | written for the vue bridge's contract score: the items it proves are named in reports 119 to 121 |

Stories 20 to 22 and 24 are about how a run uses the tree, not about the tree itself: 20 and 21 are package facts
(tasks 1245 and 1291), 22 is the cache (task 1230), and 24 is the reader's version refusal (`contract/reader_test.go`).

## Section 19: selectors, wire keys and flags

Every selector row of doc 61 section 19 is a node in `selectors/<language>`:

| Selector | Version 5 | Where |
|---|---|---|
| catch | `handler` (`caughtTypes`, `binding`, `filter`, `body`) | `catch` in every `selectors` file but Go and Vue; `filtered-catch` for `filter` |
| branch | `if` (`condition`, `then`, `else`) | every `selectors` file |
| function | `function` | every file |
| type-declaration | `type` | `selectors` php, python, csharp, typescript, go |
| parameter | `parameter` | every `selectors` file but Vue |
| block | `block` | every file |
| loop | `loop` | `selectors` (for-each), `flags` (for, with `initializer`, `condition`, `steps`) |
| return | `return` (`values[]`) | every file; `two-results` for two values |
| throw | `throw` (`value`, `cause`) | `selectors`; Go's `panic(a)` resolved to the builtin; Python's `from error` is `cause` |
| bail-out | `break`, `continue`, `throw`, `return` | `selectors` |
| expression-statement | `expression-statement` | every file |
| call | `call` (`callee`, `arguments`) | every file |
| construction | `construction` (`type` or `callee`, `arguments`, `initializer`) | `selectors`; Python's `Decimal(0)` has a `callee`, Go's `Account{total: 0}` an `initializer` |
| member-access | `access` (`subject`, `member`) | every `selectors` file |
| null-safe | `access` with `nullConditional` | `selectors` php, csharp, typescript; python and go have no null-safe access |
| self-reference | `reference` with form `self` | `selectors` php, python, csharp, typescript; go's receiver is an ordinary name |
| identifier | `reference` with form `name` | every file |
| assignment | `assignment` (`targets`, `values`) | `selectors`, `flags`, `two-results` |
| comparison | `comparison` (`operands`, links `left` and `right`) | every `selectors` file; `reversed-error-check` states the links |
| conjunction | `logical` with operator `and` | every `selectors` file |
| literal | `literal` (`literalKind`, `value`) | every file |
| import | `import` (`source`, `bindings`, `alias`) | `selectors`, `aliased-import` |

The version 4 wire keys of section 19 become: `children` and `field` the named roles; `is` the kinds and categories;
`kind` the version 5 kind; `extras` gone, each language's fact now a neutral fact (a Vue directive's `name` and
`modifiers`, a comment's `fragment`); `flags` the facts below; `declared` and `returns` the `declaredType` and
`returnType` roles; `literal` `literalKind`; `constant` `constantValue`; `inherited` and `fills-outside` the
`obligations` of a member; `role` the category; and `id`, `name`, `operator`, `refers`, `resolved` (`resolvedType`),
`resolves`, `span`, `symbol`, `target`, `value` and `modifiers` keep their names.

Every flag of section 19 is a fact in `flags/<language>`:

| Flag | Version 5 | Where |
|---|---|---|
| async | `async` on a `function` or `loop` | `flags` python, csharp, typescript |
| by-ref | parameter `passing: reference` | `flags` php, csharp (and `out`) |
| component | element `form: component` | `flags` vue |
| constant | variable `form: const` | `flags` typescript, `vue-if` |
| elif | an `if` whose `else` is an `if` | `elif` |
| generator | `generator` on a `function` | `flags` php, python, typescript |
| group | handler `group` (Python `except*`) | `flags` python |
| keyword-only | parameter `position: keyword` | `flags` python |
| keywords | parameter `collects: keywords` | `flags` python |
| named | argument `label` | `flags` php, python, csharp |
| nullable-sugar | type-expression `form: nullable` | `flags` php, csharp; `null-or-false` php |
| optional | parameter `optional` | `flags` typescript |
| promoted | a parameter its type lists in `implicitMembers` | `promoted-parameter` |
| short-ternary | fallback `trigger: falsy` | `flags` php, `null-or-false` php |
| shorthand | directive `shorthand` | `flags` vue, `selectors` vue |
| spread | argument `spread: positional` or `keyed` | `flags` php, python, typescript, go |
| step | a loop's `steps[]` | `flags` php, csharp, typescript, go |
| variadic | parameter `collects: rest` | `flags` php, python, csharp, typescript, go |
| resolves | import `resolves`, an index fact | stated under `/sample/` for the folder the sample is placed in, since the absolute path a run reads the file at differs per run: `/sample/aliased_import/money.py` |
| inherited | member `obligations`, a compiler fact | `flags` csharp: `ToString` overrides `object.ToString()`, fixed outside the project |
| constant (wire key) | `constantValue`, a compiler fact | `flags` csharp: `Limit` folds to 3 |
