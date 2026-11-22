# The generic tree contract

Every language bridge (PHP, Vue, TypeScript, Python, C#, Go) translates its own parse into this one tree,
and the engine reads only this tree. A rule is written once against it and judged the same way in every
language. A bridge does two things and nothing else: it **translates** each construct of its language into
the operation it performs, with named parts, and it **adds facts only its parser or compiler knows**
(a resolved type, a call's target, a namespace resolution, an override obligation). It never decides a rule.
The design is doc 61 revision 5; this document is its contract. Version 5.

The machine-checked form is [`tree.schema.json`](tree.schema.json): one schema owns every kind, every role
name with its cardinality, every fact and the categories. Package `contract` decodes a stream and validates it
against that schema. Strict: a key, a kind, a role or a fact the schema does not declare is an error, never
ignored. A new construct or fact means a new version of this document.

## The stream

`<bridge> <path>...` writes JSON lines to stdout, one object per line, so a reader holds one file at a time.
Each line has exactly one key, which says what the line is:

```json
{"header": {"contract": "tree", "version": 5, "language": "php", "bridge": {"name": "php-bridge", "version": "8.0.0", "reads": true}, "roots": ["/abs/src"]}}
{"file": {"path": "/abs/src/Cart.php", "language": "php", "errors": 0, "resolver": {"tool": "php-parser", "ran": true}, "root": {"id": 0, "kind": "file", "span": [0, 812, 1], "parts": {}}, "comments": []}}
{"program": {"symbols": []}}
{"trailer": {"files": 1, "resolution": {"expressions": 9992, "typed": 7823, "unjoined": 0}}}
```

1. **`header`**, always first. `contract` is `"tree"` and `version` is `5`; a reader refuses any other version.
   `language` is the bridge's language. `bridge` names the program that wrote the stream; `"reads": true`
   there says every file line names in `reads` the files its tree read (see *A file*). `roots` are the paths
   asked for, absolute, symbolic links resolved.
2. **`file`**, one per source file, in any order.
3. **`program`**, at most one, after every `file`: the facts about the whole program no file carries.
4. **`trailer`**, always last. `files` counts the `file` lines; a stream without a trailer was cut short and is
   refused. `resolution` counts what the compiler saw and resolved (`expressions`, `typed`, `calls`,
   `resolved`, and `unjoined` for facts that matched no node), so a drift in spans shows instead of hiding.

`--serve` keeps a bridge running and answers each request on stdin (`{"paths": [...], "write": [...],
"contents": {...}}`) with a full stream. A bridge that fails as a whole exits non-zero and says why on stderr.
A file it could only partly parse is still written, with `errors` counting what it could not read.

One run reads one stream per language. Their symbol spaces are separate: a symbol id means something only
beside its stream's `language`. A rule that crosses languages joins two streams by what both trees say.

## A file

| key | when | what | provenance |
|---|---|---|---|
| `path` | always | absolute, symbolic links resolved | parser |
| `language` | always | `php`, `python`, `csharp`, `typescript`, `vue` or `go` | parser |
| `errors` | always | syntax errors in the file: `0` for a file parsed whole | parser |
| `context` | the file informs but is not judged | `true`: a file read only to resolve types, targets and ancestry into. It is never reported on. Which lines are judged is decided by `contract.AskedToWrite` alone: a file asked for, or under a folder asked for, or every file when nothing was named | index |
| `test` | the bridge knows it | `true` for a file of a test project (C# `IsTestProject`). One input to the engine's `file.purpose` | compiler |
| `module` | the language names modules | the module the file is: Python `shop.cart`, Go's package import path | compiler |
| `reads` | the header says `"reads": true` | the other files whose contents shaped this tree, configuration and lockfiles included. A kept line stands while its own file and every one of these hold the same contents; this is what lets every checkout share one cache keyed on contents | index |
| `resolver` | a compiler exists for the language | `{"tool", "ran"}`: `php-parser`, `mypy`, `roslyn`, `tsc` or `go/types`, and whether it ran on this file | compiler |
| `root` | always | the file's `file` node | parser |
| `comments` | always | every comment, in source order (see *Comments*) | parser |
| `types` | nodes name their type by index | the distinct resolved types the file's nodes carry, each written once; a node's `resolvedType` is an index into it | compiler |

**A resolver that did not run.** When `resolver.ran` is `false` (mypy is absent, `go/types` failed to load
the package, Roslyn could not restore the project, there is no `tsconfig`), every compiler fact of every node
in the file is **unknown**: never known-empty, never false. A rule that needs one withholds its finding there
and the run reports the gap in its coverage. The bridge's facts from the parser stay exactly as true.

The engine reads the file's bytes itself; every span indexes into them, so no node repeats its source text.

## A node

Every node is one operation of the language, translated: `kind` says what it does, never how one parser
spells it. The parser's own node names and field names are private to the bridge and never written.

```json
{"id": 4, "kind": "handler", "span": [120, 171, 9], "catchForm": "typed",
 "parts": {"caughtTypes": [{"id": 5, "kind": "type-expression", "form": "named", "span": [127, 140, 9], "refers": "App\\ReadFailure"}],
           "binding": {"id": 6, "kind": "binding", "span": [141, 143, 9], "name": "e"},
           "body": {"id": 7, "kind": "block", "span": [145, 171, 9], "parts": {"statements": []}}}}
```

| key | when | what |
|---|---|---|
| `id` | always | unique within its file: the node's position in a pre-order walk, the root `0` |
| `kind` | always | one kind of the closed set below |
| `span` | always | `[start, end, line]`: `[start, end)` in UTF-8 bytes into the file, a byte order mark counted, trivia excluded; `line` is the 1-based line `start` is on. Vue spans point into the `.vue` file |
| `parts` | the kind has roles | the owning roles: `{"<role>": node}` or `{"<role>": [node, ...]}`, each role one the kind declares, with its declared cardinality |
| `links` | the kind declares any | non-owning edges to nodes owned elsewhere in the same file: `{"<link>": id}` or `{"<link>": [id, ...]}` |
| facts | the kind declares them | the facts listed for its kind, and the facts every node may carry (below) |

**The address.** A node is addressed by its file's identity (path and content digest), its local `id`, its
`kind` and its span. That address is what a finding names, a cache keys and a recurrence compares.

**Three orders, kept apart.** Lexical order is span order. Execution order is what each kind's entry says
(*once*, *each turn*, *after the loop*, *on exit*). A link is neither: it points at a node owned elsewhere
without making it a second child, so a derived view never inserts an executable statement.

**Facts any node may carry.**

| fact | what | provenance |
|---|---|---|
| `name` | the name as written: a declaration's, a reference's, a member's, a tag's. Never empty | parser |
| `symbol` | a declaration's symbol id (see *Symbols*) | parser |
| `refers` | the symbol id a reference, a type or an import names, whether or not it is in the scan | compiler |
| `target` | the declaration a call or construction binds to: `{"symbol", "type"?, "name", "parameters"?}` | compiler |
| `resolvedType` | the index in the file's `types` of the type the compiler gives the expression | compiler |
| `constantValue` | the compile-time value of an expression the compiler folds: `{"type", "value"}`, with an explicit `{"type": "null"}` variant for null; a literal states its value in `value` instead | compiler |
| `modifiers` | the modifiers as written, in source order: `public`, `static`, `readonly`, `final`, `abstract`, `override`, `async`, `const`, `partial`, `sealed`, `virtual` | parser |
| `effective` | what the language makes of them: `{"visibility": "public"\|"protected"\|"private"\|"internal"\|"package", "static", "abstract", "final", "readonly"}`. Go's capitalisation and TypeScript's implicit `public` land here, never in `modifiers` | parser |
| `obligations` | for a member: every declaration it overrides or implements, `[{"symbol", "origin": "project"\|"external"}]`. `external` means the signature is fixed outside the project (a package, the framework) | compiler |
| `incomplete` | `true` on a node the parser recovered from an error: its optional roles are unknown, not known-empty | parser |
| `conversion` | the format conversion of an interpolated expression, on that expression: `!r` in a Python `f"{x!r}"` | parser |

**How each item is proven.** Every kind, role, link, fact and promise a language owes is proven by a marker a person
wrote on a sample source line (`contract/samples/testdata/expected/README.md`, Markers), checked against what that
language's bridge writes; an item a language cannot have is a "Not owed" row in `contract/coverage/<language>.md`.

**Unknown, known-empty and inapplicable.** These are three different answers and a rule never confuses them:

- **inapplicable**: the role or fact is not defined on this kind (a `loop` has no `caughtTypes`). The schema
  says so, and a query that reads a role before narrowing to a kind that declares it fails validation.
- **known-empty**: the slot is defined and the evidence is complete: an absent optional role, or an empty
  list, on a node that is not `incomplete` in a file the parser read. `catch (E $e) {}` has a `body` whose
  `statements` are known-empty.
- **unknown**: the evidence is incomplete: an `incomplete` node, a compiler fact in a file whose resolver did
  not run, or a compiler fact the compiler could not resolve. A compiler fact's absence always means unknown;
  where the compiler knows a negative (a type is not nullable, a member overrides nothing) it writes it.

Unknown propagates: a proven false operand of a conjunction rules a match out, a proven true alternative of a
disjunction suffices, negating unknown gives unknown, and a count over an incomplete list is unknown wherever
the bound cannot be decided. A finding is published only on proven truth.

**Provenance.** Every fact has one fixed class, named in the schema: **parser** (the syntax), **compiler**
(the file's resolver), **index** (a join the bridge or the engine makes over files: callers, implementations,
reads) or **package** (a library's version and effects, see *The program line*). A negative fact needs complete
evidence of its class for its scope.

## Categories

A category is a named set of kinds, so a rule asks for the set instead of listing kinds:

| category | kinds |
|---|---|
| `declaration` | `module`, `type`, `field`, `constant`, `enum-case`, `alias`, `function`, `parameter`, `type-parameter`, `variable`, `declarator`, `binding`, `import`, `label` |
| `function-like` | `function`, `comprehension` (each opens a scope of its own) |
| `exit` | `return`, `throw`, `break`, `continue`, `goto` |
| `choice` | `if`, `dispatch`, `select`, `conditional`, `fallback` |
| `statement` | `block`, `if`, `dispatch`, `select`, `loop`, `break`, `continue`, `goto`, `label`, `try`, `throw`, `return`, `defer`, `resource-scope`, `expression-statement`, `variable`, `scope-declaration`, `no-op` |
| `expression` | `reference`, `call`, `construction`, `access`, `index`, `assignment`, `binary`, `comparison`, `logical`, `unary`, `fallback`, `conditional`, `type-test`, `cast`, `await`, `yield`, `literal`, `default-value`, `collection`, `interpolation`, `function` with form `closure`, `arrow` or `lambda`, `comprehension` |
| `markup` | `element`, `text`, `attribute`, `directive`, `embedded-expression` |

## Kinds

Each entry names the kind's roles with their cardinality (`role` required, `role?` optional, `role[]` an
ordered list of zero or more), its links, its facts, and when its parts are evaluated where that matters.

### Program and files

- **`file`**: the root of every file line. `modules[]` (PHP may declare several namespaces in a file; every
  other language has one). Facts are the file's own (see *A file*).
- **`module`**: a namespace, package or module scope. `imports[]`, `declarations[]`, `statements[]` (the
  executable top-level code of a script, in source order). Fact `name?` (the namespace or package as written;
  absent for PHP's global namespace and a TypeScript file).
- **`import`**: `bindings[]` (`import-binding`), `alias?` (`binding`: `import x as y`, Go's `import y "x"`).
  Facts: `source` (the module or path as written), `form` (`module`, `names`, `wildcard`, `side-effect`),
  `typeOnly`, `relativeLevel` (Python's leading dots), `resolves` (the absolute path of the file it reaches;
  index), `refers` (the module's id; compiler). PHP `use function` and `use const` are `form: names` whose
  bindings say what they name.
- **`import-binding`**: `alias?` (`binding`). Facts `name` (what it imports), `refers`.

### Declarations

- **`type`**: a class, interface, trait, enum, struct, record or object type declaration. `bases[]` (what it
  extends), `interfaces[]` (what it implements), `mixins[]` (PHP traits it uses), `typeParameters[]`,
  `attributes[]` (`annotation`), `writtenMembers[]` (every member as written, in source order). Links
  `implicitMembers[]` point at the written node an implicit member comes from (a promoted constructor
  parameter, a record's positional parameter); links `satisfies[]` name the interfaces Go satisfies implicitly
  (see *Go*). Facts `name?` (absent for an anonymous class), `symbol`, `form` (`class`, `interface`, `trait`,
  `enum`, `struct`, `record`, `object`).
- **`field`**: a property or field declaration. `declarators[]`, `declaredType?`, `attributes[]`. Facts
  `modifiers`, `effective`, `optional` (TypeScript `name?`: the property may be left out). A PHP property hook is a `function` of form `get` or `set` among the owning type's
  `writtenMembers`, and the field links it in `hooks[]`.
- **`constant`**: `declarators[]`, `declaredType?`, `attributes[]`.
- **`declarator`**: one name a field, constant or variable declares. `binding`, `initializer?`.
- **`enum-case`**: `value?`, `attributes[]`. Facts `name`, `symbol`.
- **`alias`**: a type alias. `typeParameters[]`, `underlyingType`. Facts `name`, `symbol`.
- **`type-parameter`**: `constraint?`, `default?`. Fact `name`.
- **`function`**: every function-like. `receiver?` (`parameter`: Go's receiver, Python's `self` or `cls`), `typeParameters[]`,
  `parameters[]`, `returnType?`, `body?`, `attributes[]`. `body` is a `block`, or an expression for an arrow
  or lambda written with an expression body; an absent body (an abstract or interface method, a declaration
  only) is known-empty and differs from an empty block. Facts `name?`, `symbol`, `form` (`function`, `method`,
  `constructor`, `get`, `set`, `closure`, `arrow`, `lambda`, `local`), `async`, `generator`. A closure's
  `captures[]` are the bindings it takes from outside as written (PHP `use`). A closure, arrow and lambda open
  their own scope.
- **`parameter`**: `binding?` (absent only for an unnamed parameter of a function type: Go's `func(int) error`),
  `declaredType?`, `default?`, `attributes[]`. Facts `passing` (`value`,
  `reference`, `out`, `in`), `collects` (`none`, `rest`, `keywords`), `position` (`either`, `positional`,
  `keyword`), `optional` (TypeScript `x?`), `evaluated` (when its default is evaluated: `call`, or
  `definition` for Python). A promoted constructor parameter (PHP, a TypeScript parameter property) is
  the one written node of the field it declares: its `modifiers` are the field's, and its type lists it in `implicitMembers`.
- **`variable`**: a declaration with a keyword (`let`, `const`, `var`, C# typed locals, Go `var`) or a type (Python's
  `x: T = v` outside a class, form `annotated`). A
  `using` declaration (C# `using var`, TypeScript `using` and `await using`) is form `using`: its value is released
  where the enclosing block ends; the statement that holds a body is a `resource-scope`.
  `declarators[]`, `declaredType?`. Fact `form`. Go's `:=` is an `assignment` with mode `define`.
- **`binding`**: a name a declaration introduces. Facts `name`, `symbol?`.
- **`annotation`**: an attribute, annotation or decorator applied to a declaration. `callee` (a reference or
  access naming it), `arguments[]`. Facts `name`, `refers`.
- **`scope-declaration`**: Python `global` and `nonlocal`, PHP `global`. `bindings[]`. Fact `form`.

### Statements

- **`block`**: `statements[]`, in source order.
- **`expression-statement`**: `expression`: an expression whose value is discarded.
- **`no-op`**: an empty statement: `;`, Python `pass`, an empty Go statement.
- **`if`**: `initializer?` (Go's `if x := f(); x > 0`, evaluated once, first), `condition`, `then`, `else?`.
  An `else` that is itself an `if` is an else-if (`else if`, `elif`, `elseif`); an `else` that is a `block`
  holding an `if` is an if nested in an else. Structure alone tells the two apart.
- **`dispatch`**: a switch, match or type switch. `subject?` (Go's `switch {}` has none; a type switch's
  subject is a `type-test`), `arms[]`. Facts `form` (`switch`, `match`, `type-switch`), `exhaustive` (only
  where the compiler proves it).
- **`arm`**: `patterns[]` (none for a default arm), `guard?`, `body` (a block, or an expression in a match).
  Facts `default`, `fallsThrough` (the arm's end continues into the next: a C or PHP `case` without a jump, Go's
  `fallthrough`). Arms keep their order, and patterns are tried in it.
- **`select`**: Go's `select` over ready channels. `arms[]` (`select-arm`). Its own kind, since a dispatch
  would misstate it: no subject is compared, the first ready communication runs.
- **`select-arm`**: `communication?` (a send or receive statement; absent for `default`), `body`.
- **`loop`**: `initializer[]` (once, first), `condition?` (each turn, before the body; after it for
  `do-while`), `iterable?` (once), `bindings[]` (each turn: a for-each's key and value), `body` (each turn),
  `steps[]` (each turn, after the body), `normalCompletion?` (after the loop, only when it ends without a
  break: Python's `for…else`). Facts `form` (`for`, `for-each`, `while`, `do-while`, `infinite`), `async`
  (`for await`, `async for`).
- **`break`** and **`continue`**: `label?` (a reference to a label, as written). Link `destination?` (the
  loop, switch, select or labelled statement it leaves; the reader derives it from the tree when a bridge does not). Fact `depth?` (PHP `break 2`).
- **`goto`**: `destination` (a reference, possibly unresolved). Link `label?` (the label of that name in its
  function, derived by the reader).
- **`label`**: `binding`, `statement?`.
- **`try`**: `body`, `handlers[]`, `normalCompletion?` (Python's `try…else`: after the body, only when it
  raised nothing), `finally?` (on exit, always).
- **`handler`**: `caughtTypes[]` (the types it catches, written; none for a catch-all), `binding?`,
  `filter?` (C# `when`), `body`. Facts `catchForm` (required: `typed` or `catch-all`), `group` (Python
  `except*`). `catch (Throwable $e)` is `typed`; C#'s `catch {}` and Python's bare `except:` are `catch-all`.
  Go has no handler.
- **`throw`**: `value?` (absent for a rethrow: `throw;`, bare `raise`), `cause?` (Python `raise X from Y`).
  A wrapped exception passed as a constructor argument stays an argument of its `construction`.
- **`return`**: `values[]` (Go returns several; every other language zero or one). Fact `namedResults`
  (`true` on a bare Go `return` in a function with named results: it returns them, so its `values` are not
  the known-empty answer).
- **`defer`**: Go's `defer`. `call`: evaluated on exit from the function, last deferred first. Its own kind,
  since an expression statement would say it runs where it is written.
- **`resource-scope`**: `with`, `using`, `lock`, `async with`. `resources[]` (each a `declarator` or an
  expression, acquired once in order), `body`. Facts `form` (`with`, `using`, `lock`), `async`. Each resource
  is released on exit, in reverse order.

### Expressions

- **`reference`**: a name read or written. Facts `name`, `form` (`name`; `self` for `$this`, `this`, `self`;
  `parent` for `parent`, `super`, `base`; `static`), `refers` (for a class named as `self`, `static` or `parent`, the
  class it names: the enclosing one, or the one it extends). Link `binding?` (the local `binding` it names, or the `import-binding` of a name imported under its own name,
  where it is in the same file).
- **`call`**: `callee` (the expression called: a `reference`, or an `access` for a method), `typeArguments[]`,
  `arguments[]`. Facts `nullConditional`
  (`?->`, `?.()`), `target`. A language construct that behaves as a function (`isset`, `empty`, `echo`,
  `print`, `unset`) is a call whose callee refers to the language's own symbol for it. PHP's pipe
  `value |> callable` is the call `callable(value)`: the callable its callee, the value its one argument.
- **`construction`**: `type?` (the type written after `new`, or an anonymous class's `type`), `callee?` (a
  call of a class: Python `Money(...)`, which the compiler resolves to a construction; its type arguments are the
  written type's own, `List<int>`), `arguments[]`, `initializer?` (a C# object or collection initializer, a `collection`). Fact `target`.
- **`argument`**: `value`. Facts `label?` (a named argument's name), `passing` (`value`, `reference`, `out`,
  `in`), `spread` (`none`, `positional`, `keyed`).
- **`access`**: `subject`, `member` (a `reference` naming the member). Facts `form` (`instance`, `static`),
  `nullConditional` (`?->`, `?.`).
- **`index`**: `subject`, `key`. Fact `nullConditional`.
- **`assignment`**: `targets[]` (Python's `a = b = c` has two; a destructuring target is a `pattern`),
  `values[]` (Go's `a, b = b, a` has two; none for `i++`). Facts `mode` (`assign`, `define` for Go `:=`,
  `compound`, `fallback` for `??=`, `increment`, `decrement`), `operator?` (a compound's semantic operator),
  `prefix` (`++i`).
- **`binary`**: `left`, `right`, both evaluated. Fact `operator`: `add`, `subtract`, `multiply`, `divide`,
  `modulo`, `power`, `concatenate`, `bit-and`, `bit-or`, `bit-xor`, `shift-left`, `shift-right`, `xor`
  (PHP's logical `xor`, which evaluates both sides).
- **`comparison`**: `operands[]` (two, or more for a Python chain `a < b <= c`, each evaluated once). Links
  `left` and `right` (the first and the last operand). Fact `operators[]`, one between each pair: `equal`
  (`==`), `not-equal` (`!=`, `<>`), `identical` (`===`, Python `is`), `not-identical` (`!==`, `is not`),
  `less`, `less-or-equal`, `greater`, `greater-or-equal`, `compare` (`<=>`), `in`, `not-in`. Fact `operator`
  is set too when there is exactly one.
- **`logical`**: `left`, `right`, the right evaluated only when the left does not decide. Fact `operator`
  (`and`, `or`). Its own kind: a `binary` would say both sides always run.
- **`unary`**: `operand`. Fact `operator` (`not`, `negate`, `plus`, `bit-not`, `typeof`, `delete`, `void`,
  `reference-of`, `dereference`, `receive` for Go's `<-ch`, `assert-present` for C# and TypeScript's
  `x!`, which only tells the checker the value is there). C#'s `typeof(T)` is a `typeof` whose operand is the
  `type-expression` written.
- **`fallback`**: `subject`, `replacement` (evaluated only when the trigger holds). Fact `trigger` (`null` for
  `??`, `falsy` for PHP's short ternary `a ?: b`).
- **`conditional`**: `condition`, `consequent`, `alternative`.
- **`type-test`**: `subject`, `testedType?` (absent only on a type switch, whose arms carry the types), `binding?` (C# `x is Money m`, a type switch's bound name). Facts
  `negated` (`!is`, `not isinstance`), `form` (`instanceof`, `is`, `isinstance`, `type-switch`). Python's
  `isinstance(x, A)` is a type-test where the compiler resolves the callee to the builtin.
- **`cast`**: `operand`, `targetType`. Fact `form` (`checked`: C# `(T)x` or PHP `(int)$x`, which converts or
  fails; `safe`: C# `as`, which yields null; `assertion`: TypeScript `x as T` and `<T>x`, which only tells the
  checker).
- **`await`**: `operand`. Its own kind: it suspends.
- **`yield`**: `value?`. Fact `delegates` (`yield from`, `yield*`). Its own kind: it suspends and resumes.
- **`literal`**: Facts `literalKind` (`string`, `integer`, `float`, `boolean`, `null`, `undefined`, `bytes`,
  `character`, `ellipsis`) and `value` (decoded and dialect-independent: a string without quotes or escapes, a
  number as a decimal string so no width is lost, `true` or `false`; none for `null` and `undefined`). Python
  `None` and PHP `null` are `null`. Go's `nil`, `true` and `false` are literals when the compiler resolves them
  to the universe, and references otherwise: a shadowed `nil` is an ordinary name.
- **`default-value`**: C# `default` and `default(T)`. `writtenType?`. Fact `resolvedType` (the contextual type).
- **`collection`**: `entries[]`, in order. Fact `form` (`list`, `array`, `set`, `map`, `tuple`, `object`).
- **`entry`**: `key?`, `value`. Fact `expansion` (`none`, `positional`, `keyed`: `...$x`, `*x`, `**x`).
- **`interpolation`**: `parts[]`, in order: `text` parts (fact `value`) and expressions. A PHP `"…$x…"`, a
  heredoc, a TypeScript template, a Python f-string, a C# `$"…"`. A format conversion is fact `conversion` on
  its expression part (`!r`).
- **`comprehension`**: a Python list, set, dict or generator comprehension. `element` (or `key` and `value`
  for a dict), `clauses[]` (`comprehension-clause`, in order). Fact `form`. It opens its own scope.
- **`comprehension-clause`**: `bindings[]` and `iterable` for a `for` clause, `condition` for an `if` clause.
  Facts `form` (`for`, `if`), `async`.

### Types as written

- **`type-expression`**: a type as written. `typeArguments[]`, `members[]` (a union's or intersection's alternatives),
  `element?` (an array's or pointer's element), `parameters[]` and `returnType?` (a function type). Facts
  `form` (`named`, `keyword`, `union`, `intersection`, `nullable`, `array`, `function`, `literal`,
  `parameter`, `pointer`, `map`, `object`, `opaque`; `object` is an inline object type, its fields in its resolvedType), `refers` (a named type's id). `?T` is form `nullable`. A `keyword` type carries the keyword as written as its `name` (`int`, `void`,
  `string`, `None`), so two keyword types are told apart without a resolver; a keyword naming a class (`self`,
  `static`, `parent`) also `refers` to that class.
- **`tuple-type`**: `elements[]` (`tuple-element`). Separate from a `return` of several values, so a single
  tuple-valued expression and two result expressions stay distinguishable.
- **`tuple-element`**: `type`. Fact `label?`.

### Patterns

- **`pattern`**: a destructuring target or a case pattern. `subpatterns[]` (its elements in order; an element that
  only names a variable is that `binding`), `bindings[]` (what a rest or binding pattern names), `constrainedType?`,
  `value?`. Fact `form` (`wildcard`, `binding`, `value`, `type`, `sequence`, `mapping`, `record`, `or`, `and`,
  `not`, `relational`, `rest`), and `operator` on a relational pattern, the comparison it makes (`greater`, as in C#'s
  `> 0`).

### Markup (Vue templates)

- **`element`**: `attributes[]` (`attribute` and `directive`), `children[]` (`element`, `text`,
  `embedded-expression`). Facts `tag`, `form` (`native`, `component`: a tag the compiler resolves to a
  component, including `<component :is>` and `<transition>`), `resolves` (a component tag's file).
- **`text`**: Fact `value`.
- **`attribute`**: `value?` (a `literal`). Fact `name`.
- **`directive`**: `argument?` (a static `reference` or a dynamic expression), `value?` (the expression),
  `bindings[]` and `iterable?` (`v-for`), `pattern?` (`v-slot`). Link `body` (the element it sits on).
  Facts `name` (`if`, `else-if`, `else`, `for`, `show`, `model`, `on`, `bind`, `slot`, or a custom name),
  `modifiers[]`, `shorthand` (`:x`, `@x`, `#x`).
- **`embedded-expression`**: `expression`: a `{{ }}` interpolation. Embedded expressions share reference,
  call and scope semantics with the script.

### Unsupported constructs

- **`unsupported`**: a construct this version does not translate. `parts[]` (its sub-expressions, so the
  references and calls inside are still read). Fact `construct` (a stable name for what it is, for coverage).
  An unsupported construct is distinguishable from a program with none of a construct: a rule over the kind it
  would have been reports its coverage as incomplete instead of reporting nothing.

## Derived reads

The engine computes these from the tree, once, for every language. No bridge writes them.

- **`ownDescendants`**: a node's descendants in pre-order, bounded by nested `function`, `comprehension` and
  `type` nodes, which are included but not entered.
- **`file.purpose`**: `ordinary`, `test` or `test-fixture`, from the project's configuration, the file's
  identity and the evidence: the bridge's `test`, a test framework's resolved types. A rule reads this one enum
  and never a path.
- **The program indexes**: by kind, by parent and role, by file and module, by symbol: callers,
  implementations, inheritance, overrides, module edges.
- **Parent and role**: a node carries no parent; the reader builds the links once, and a node's role is the
  name of the role it fills in its parent.

## Types

A resolved or written type is an object, so its structure survives and nothing re-parses a string.

| key | when | what |
|---|---|---|
| `text` | always | the canonical spelling, fully qualified where it names a class: `?\Shop\Money`, `str \| None` |
| `kind` | always | `named`, `keyword`, `parameter`, `union`, `intersection`, `array`, `tuple`, `object`, `function`, `literal`, `pointer`, `map`, `opaque` |
| `name` | `named`, `keyword`, `parameter` | the qualified name, the keyword, or the parameter's name |
| `args` | generics, arrays, maps | the type arguments in order; a map's key then its value |
| `members` | `union`, `intersection`, `tuple` | the member types in order |
| `fields` | `object` | `[{"name", "type"?, "optional"?}]` |
| `parameters`, `returns` | `function` | the parameter types and the return type |
| `value` | `literal` | the value, decoded as a literal's is |
| `nullable` | the compiler can say | `true` or `false`; absent only where the compiler cannot say (unknown), as for a type parameter. A Go nil slice or map is an empty one, so neither is nullable; a nil pointer, interface, func or channel is |
| `valueType` | the compiler can say | `true` for a value type (a C# struct or enum, a Go basic type, struct or array), `false` for a reference; absent only where the compiler cannot say |
| `element` | a collection whose element is known | the element type, one level |
| `constructs` | the expression names a class | the class a call of it builds |
| `origin` | always | `written`, `docblock`, `attribute`, `inferred`, `compiler` |

## Symbols

A **symbol id** is one string naming a declaration, so a call, a name and a doc reference join to it by string
equality. Each language spells ids its own fixed way:

| language | a type | a member | a function |
|---|---|---|---|
| php | `Shop\Cart` | `Shop\Cart::add`, `Shop\Cart::$items`, `Shop\Cart::MAX` | `Shop\total` |
| python | `shop.cart.Cart` | `shop.cart.Cart.add` | `shop.cart.total` |
| csharp | `global::Shop.Cart` | `global::Shop.Cart.Add(global::System.Int32)` | a local function: its member's id, `.`, its name |
| go | `example.com/shop/cart.Cart` | `example.com/shop/cart.Cart.Add` | `example.com/shop/cart.Total` |
| typescript, vue | `<path>#Cart` | `<path>#Cart.add` | `<path>#total`, `<path>#default` |

One id may name several declarations (a C# partial class, a TypeScript overload set, a Python `@overload`); the
engine keeps ids as a multimap. C# spells a generic's original definition. PHP class, function and method names
compare case-insensitively. A TypeScript standard-library declaration is its global name (`Array.map`).

## Comments

Comments are a list on the file, not nodes. Each names the node it belongs to.

| key | when | what |
|---|---|---|
| `id` | always | its position in the file's `comments` |
| `form` | always | `line`, `block`, `doc` (`/** */`, `///`, a docstring) or `markup` (`<!-- -->`) |
| `text` | always | the full text, markers included |
| `span` | always | as a node's |
| `attached` | it leads or trails a node | the node's id: the outermost node that starts at the first token after it, or that ends on its line before it |
| `trailing` | it sits after code on its line | `true` |
| `refs` | a doc comment names code | `[{"text", "symbol"?, "owner"?, "ownedHere"?, "blind"?}]` |
| `fragment` | the bridge tried to parse its words as code | `{"context", "diagnostics", "statements", "expressionKind"?}`: the parse context tried (`statement`, or `array-element` for PHP), the parser's diagnostics count, how many statements it parsed, and the neutral kind of a lone expression. Which fragments are commented-out code is the shared rule's decision, never the bridge's |
| `description` | a doc comment says something in prose | `["line", …]`: its prose lines in order, blank lines kept, with the comment's markers (`/**`, `*`, `///`, `#`, quotes) and its tags left out, as the language's own doc parser reads it |
| `tags` | a doc comment has tagged parts | `[{"name", "text"?, "type"?, "subject"?}]`: each `@tag` of PHPDoc or JSDoc, each XML element of a C# doc comment, each section of a Python docstring (`Args`, `Returns`, `Raises` …), with the type it writes and the name it is about when it has them. A rule asks what a doc says through these, the same way in every language |

A Python docstring stays in the tree as an `expression-statement` and is also listed here as a `doc` comment
attached to what it documents, so every documentation rule reads one list.

## The program line

| key | what | provenance |
|---|---|---|
| `symbols` | declarations outside the scanned files: PHP reflects them from vendor, C# reads referenced assemblies. Each `{"symbol", "kind", "name", "file"?, "internal"?, "extends"?, "implements"?, "uses"?, "modifiers"?, "members"?}`, closed over every ancestor and every class a member's type names | compiler |
| `unreadable` | `[{"symbol", "reason"}]`: classes the project's own loader failed on | compiler |
| `loader` | why the project's own loader was not used, absent when it was | compiler |
| `packages` | the installed packages: `[{"name", "version", "ecosystem"}]` (`composer`, `pip`, `nuget`, `npm`, `go`) | package |
| `packageFolders` | Python: the folders holding `__init__.py`, absolute | index |
| `aliases` | TypeScript and Vue: `[{"prefix", "path"}]`, the module path aliases imports resolved through | compiler |

**Package facts.** What a library does enters the engine as data keyed by package and version, never as code in
a rule: `{"package", "versions", "symbol", "effect", "executionPoint"}`, where `effect` is one of a closed list
the schema names (`executes-query`, `returns-empty-collection`, `transforms-input`, `cast-on-from`, …) and
`executionPoint` says when it happens (`call`, `iteration`, `construction`). A package fact identifies the
resolved symbol it applies to and cannot see its caller's tree, the sin or a threshold. The same resolved call
has the same effect wherever it stands; a look-alike method on another symbol has none.

## Constructs decided

Where a construct could have been bent into an existing kind, this version decides it once, by the rule that a
new kind is added only where an existing one would misstate execution:

| construct | decided as | why |
|---|---|---|
| Go `defer` | kind `defer` | it runs on exit, not where it stands |
| Go `select` | kind `select` | the first ready communication runs; nothing is compared |
| `yield`, `yield from` | kind `yield` | it suspends and resumes |
| `await` | kind `await` | it suspends |
| `with`, `using`, `lock` | kind `resource-scope` | each resource is released on exit, in reverse |
| `instanceof`, `is`, `isinstance` | kind `type-test` | it asks what a value is, and may bind it |
| closures, arrows, lambdas | kind `function`, forms `closure`, `arrow`, `lambda` | the same operation as any function, with a scope of its own |
| Python comprehensions | kind `comprehension` | its own scope, and clauses evaluated per element |
| casts, `as` | kind `cast`, forms `checked`, `safe`, `assertion` | the three differ in what a failure does |
| `goto`, labels | kinds `goto` and `label` | a jump the flow rules follow |
| `&&`, `\|\|`, `and`, `or` | kind `logical` | the right side may not run |
| `??`, PHP `?:` | kind `fallback` | the replacement runs only when the trigger holds |
| `i++`, `+=`, `??=` | kind `assignment` with a mode | they write a target |
| Go `:=` | kind `assignment`, mode `define` | it binds and writes at once |
| `isset`, `echo`, `print` | kind `call` to the language's own symbol | they evaluate like a call |
| PHP property hooks | kind `function`, forms `get` and `set` | a member with a body |

## Worked example: a swallowed catch

Every empty handler swallows the failure it accepts, whatever it catches (question 35). These three are the same
tree, and the rule reads it once:

```php
try { $this->reader->read($path); } catch (ReadFailure $e) { }
```

```python
try:
    reader.read(path)
except ReadFailure as e:
    pass
```

```csharp
try { reader.Read(path); } catch (ReadFailure e) when (e.Retry) { }
```

```json
{"kind": "try", "parts": {
  "body": {"kind": "block", "parts": {"statements": [{"kind": "expression-statement", "parts": {"expression": {"kind": "call"}}}]}},
  "handlers": [{"kind": "handler", "catchForm": "typed",
    "parts": {"caughtTypes": [{"kind": "type-expression", "form": "named", "refers": "App\\ReadFailure"}],
              "binding": {"kind": "binding", "name": "e"},
              "body": {"kind": "block", "parts": {"statements": []}}}}]}}
```

Python's `pass` is a `no-op` statement, which the rule filters out before it counts, so that body's effective
statements are empty too. C#'s `when` is the handler's `filter`, kept for the rules that read it and ignored by
this one. The rule, over every language:

```json
["and", ["=", "$site.kind", "handler"], ["=", ["count", ["filter", "$site.body.statements", "s", ["!=", "$s.kind", "no-op"]]], 0]]
```

## Worked example: a Go error guard

```go
if err != nil {
    return nil, err
}
if err != nil {
    return nil, nil
}
```

```json
{"kind": "if", "parts": {
  "condition": {"kind": "comparison", "operators": ["not-equal"], "operator": "not-equal",
    "parts": {"operands": [{"kind": "reference", "name": "err", "resolvedType": 3},
                           {"kind": "literal", "literalKind": "null"}]}},
  "then": {"kind": "block", "parts": {"statements": [
    {"kind": "return", "parts": {"values": [{"kind": "literal", "literalKind": "null"},
                                            {"kind": "literal", "literalKind": "null"}]}}]}}}}
```

The first guard returns the error it tested, so the failure is passed on. The second returns two proven absent
values, so the failure is swallowed: the rule matches it, by the condition's operands in either order and the
`then` block's single exit. The error's type is the compiler's (`resolvedType` names Go's `error`), and `nil` is
a `literal` only because the compiler resolved it to the universe's `nil`. In a file whose resolver did not run,
`nil` is a `reference` with unknown `refers` and `err` has no type, so the rule cannot prove the guard and reports
nothing there. A bare `return` in a function with named results carries `namedResults`, so its empty `values` are
never read as returning nothing.

## Go

Go declares a method outside its type: a `function` with a `receiver` belongs to the type its receiver names,
joined by symbol id. An embedded field is a `field` whose `declarator` binds no name of its own, only a
`declaredType`. Several results are a function's `returnType` of kind `tuple-type`, and a named result is a
`tuple-element` with a `label`. Interfaces are satisfied implicitly: the compiler links a type's `satisfies[]` to
every interface of the module it implements, so a rule reads one relation in every language. Capitalisation is
visibility and lands in `effective.visibility`. `panic(x)` where the compiler resolves `panic` to the builtin is a
`throw` with `value` x; a shadowed `panic` is a call. Go's types come from `go/types` over the module's own
packages and the standard library of the Go installation; a type reached only through any other import is unknown,
never guessed. A file this
platform does not build is written with its tree and no compiler facts.

## C#

A field declaration's names are its `declarators`, one per variable, so `int a, b;` is one `field` with two. A
name read in an expression refers, through the compiler, to the field, property or event it reads. A collection
type's `element` is what it enumerates, or a dictionary's value; a `string` holds none.

## Python

A decorator is an `annotation`. An `except` binding is the handler's `binding`. A parameter's default is
evaluated once, at definition (`evaluated: "definition"`), so the shared rule about mutable defaults reads it
without knowing Python.
A class deriving `typing.Protocol` is a `type` of form `interface`. A name with a leading underscore (not a dunder)
is `effective.visibility` private; a `staticmethod` or `classmethod` carries the modifier `static`.
A class-body attribute is a `field` whose `effective.static` is true when every instance shares it (an
unannotated assignment, or a `ClassVar`) and false when each instance holds its own (an annotation, a dataclass field).
An instance attribute is declared by its first `self.x = ...` in `__init__` (for a name the class body does not declare):
the member reference of that target is the one written node of the field, with symbol `module.Class.x`, and the class
lists it in `implicitMembers`, as a promoted constructor parameter is listed elsewhere.
`type(x)(...)` and `x.__class__(...)` build an object of `x`'s class, so each is a `construction` whose resolved type is that class.

## Vue

The file's root `file` holds one `module` per `<script>` block, and the template's elements under a `template`
module, all with spans into the `.vue` file. A `<script setup>` beside a plain `<script>` is two modules of one
component, and a template's references link to the script bindings they read.
