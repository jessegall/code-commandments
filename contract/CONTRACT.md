# The generic tree contract

Every language bridge — PHP, Vue, TypeScript, Python, C# — answers in this one shape, and the engine
reads only this shape. A bridge parses its language and resolves what only that language's compiler or
checker can know. Everything it can leave to the engine, it leaves. Version 1.

What the engine must be able to read is listed per engine in [`inventory/`](inventory/). The mapping
from each of those facts to a field here is in [`COVERAGE.md`](COVERAGE.md). The machine-checked form
is [`tree.schema.json`](tree.schema.json), and package `contract` decodes and validates a stream
against it.

## The stream

`<bridge> <path>...` writes JSON lines to stdout: one object per line, so a reader holds one file at a
time however large the project. Each line has exactly one key, and that key says what the line is:

```json
{"header": {"contract": "tree", "version": 1, "language": "php", "bridge": {"name": "php-bridge", "version": "4.374.0"}, "roots": ["/abs/src"]}}
{"file": {"path": "/abs/src/Cart.php", "language": "php", "errors": 0, "root": {"id": 0, "kind": "Stmt_Namespace", "role": "statement", "span": [6, 812, 3]}, "comments": []}}
{"program": {"symbols": [], "packages": []}}
{"trailer": {"files": 1, "resolution": {"expressions": 9992, "typed": 7823, "calls": 410, "resolved": 398}}}
```

1. **`header`**, always first. `contract` is always `"tree"`. `version` is the contract version. A reader
   names the versions it reads and refuses any other, so every change to this document raises it.
   `language` is the bridge's language (see *Languages*); a Vue bridge writes `vue`, with its script
   blocks' TypeScript trees inside each file. `bridge` names the program that wrote the stream.
   `roots` are the paths it was asked for, absolute and with symbolic links resolved.
2. **`file`**, one per source file, in any order.
3. **`program`**, at most one, after every `file`: the facts about the whole program that no single
   file carries and the engine cannot derive (see *The program line*).
4. **`trailer`**, always last. `files` counts the `file` lines. A stream without a trailer was cut
   short, and a reader refuses it. `resolution` says how much the bridge's resolver saw and how much
   it could resolve. All four counts are optional, and each bridge writes the ones it has.

`--serve` keeps the bridge running: it answers each request on stdin, one JSON line (`{"paths": [...],
"write": [...]}`, as the mypy bridge takes it today), with a full stream, header to trailer. A bridge
that fails as a whole exits non-zero and writes why to stderr. A file it could only partly parse is
still written, with `errors` counting what it could not read.

**Strict.** Every object in this contract is closed: a key that is not documented here is an error,
never ignored. That includes the per-language `extras` (see *Extras*), which are closed per language
too. A new fact means a new version of this document.

**Absent, never guessed.** A fact the bridge could not resolve is left out. It is never `null`, and
never a best guess. `null` appears only as a literal's own `value`.

## A file

| key | when | what |
|---|---|---|
| `path` | always | absolute, with symbolic links resolved |
| `language` | always | the language this file's `root` is written in |
| `errors` | always | syntax errors in the file: `0` for a file parsed whole |
| `test` | the bridge knows it | `true` for a file of a test project (C#: `IsTestProject`) |
| `module` | the language names modules | the dotted module the file is (Python `shop.cart`) |
| `root` | always | the file's root node |
| `comments` | always | every comment in the file, in source order (see *Comments*) |

The engine reads the file's bytes itself: `path` is where they are, and every span indexes into them.

## A node

Every syntax node, nested as the language nests them. Tokens, whitespace and comments are not nodes.

| key | when | what |
|---|---|---|
| `id` | always | the node's number, unique within its file: its position in a pre-order walk, the root `0` |
| `kind` | always | the language's own name for the node (see *Languages*): `Expr_MethodCall`, `InvocationExpression`, `Call` |
| `role` | always | `statement`, `expression`, `member` (a declaration inside a type or module body), `type` (a type as written), `pattern`, `markup` (a template element, attribute or text) or `other` |
| `is` | the node answers any | the language-neutral questions it answers yes to (see *Neutral kinds*) |
| `span` | always | `[start, end, line]`: `[start, end)` in UTF-8 bytes into the file, a byte order mark counted, trivia excluded; `line` the 1-based line `start` is on |
| `field` | the parent names its slots | the slot this node fills in its parent: `var`, `args`, `test`, `body`. A list slot repeats the same field on each item, in order |
| `children` | it has any | its child nodes, in source order |
| `name` | declarations, names, identifiers, members, elements, attributes | the name as written (`add`, `Cart`, `self`, `div`) |
| `text` | literals, template text, attribute values | the source as written: `'x'` with its quotes, `0x1F`, raw template text |
| `literal` | literals | `string`, `number`, `bool`, `null`, `undefined`, `bytes`, `ellipsis`, `interpolated`, `format` |
| `value` | literals the language folds | the literal's value decoded: a string without quotes or escapes, a number, `true`/`false`, `null` |
| `operator` | binary, unary, assignment, update expressions | the operator token: `==`, `??`, `+=`, `!`, `not`, `await`, `instanceof` |
| `modifiers` | the node carries any | as written, in order: `public`, `static`, `readonly`, `final`, `abstract`, `override`, `async`, `const`, `partial`, `out` |
| `flags` | the node has any | syntax facts that are not modifier keywords (see *Flags*) |
| `declared` | declarations with a written type | the type the source declares for a parameter, property, field or variable (see *Types*) |
| `returns` | function-likes with a written return type | the declared return type |
| `resolved` | expressions the resolver typed | the type the compiler or checker gives the expression |
| `symbol` | declarations | the declaration's symbol id (see *Symbols*) |
| `refers` | names and imports that resolve | the symbol id of what the name refers to: a class name to its class, an import binding to what it imports |
| `target` | calls and constructions that resolve | the declaration called (see *Symbols*) |
| `resolves` | imports and component tags that resolve | the absolute path of the file the import or tag reaches |
| `constant` | expressions with a compile-time value | `true`: a literal, an enum case, a constant, or arithmetic on them, as the compiler folds it |
| `inherited` | members that override or implement another | `true`, decided against the whole hierarchy, not by an `override` keyword |
| `comments` | comments lead or trail it | the ids of the comments attached to it, in source order: EVERY one, not only the last docblock |
| `extras` | the language has facts of its own on it | `{"<language>": {...}}`, closed per language (see *Extras*) |

A node carries no parent. Its parent is the node whose `children` hold it, and a reader builds the
parent links once as it reads the file, because nearly every question a detector asks climbs them.

### Neutral kinds

`kind` is the language's own vocabulary, so a detector written for one language reads that language's
kinds. A rule that holds for every language asks `is` instead. It is a list drawn from this closed set:

| value | the node is |
|---|---|
| `function` | a function-like with a body: function, method, constructor, accessor, closure, arrow, lambda |
| `type-declaration` | a declaration of a type: class, interface, trait, enum, struct, record, type alias |
| `block` | a statement list |
| `branch` | a branching construct: if, else-if, switch, match, ternary, conditional |
| `loop` | a loop: for, foreach, for-in/of, while, do |
| `return` | a return |
| `throw` | a throw or raise |
| `bail-out` | a statement that leaves its block: return, throw, break, continue |
| `expression-statement` | an expression used as a statement |
| `call` | a call: function, method, static |
| `construction` | a construction: `new`, object creation, a call of a class |
| `assignment` | an assignment, compound or not |
| `literal` | a literal |
| `import` | an import or use |
| `catch` | a catch or except clause |
| `null-safe` | a null-safe access: `?->`, `?.` |

### Flags

A list drawn from this closed set. Each appears only where the language has the construct.

| value | on |
|---|---|
| `variadic` | a parameter that collects the rest: `...$x`, `*args`, `params` |
| `keywords` | a parameter that collects keyword arguments: `**kwargs` |
| `by-ref` | a by-reference parameter or argument |
| `promoted` | a PHP constructor parameter promoted to a property |
| `keyword-only` | a Python parameter after `*` |
| `optional` | an optional parameter or field: TS `x?`, a parameter with a default |
| `spread` | an unpacked argument or element: `...$x`, `*x`, `**x` |
| `named` | an argument passed by name |
| `async` | an async function, loop, `with` or comprehension |
| `generator` | a function that yields |
| `short` | a short form: PHP `?:`, a shorthand Vue directive `:x` / `@x` / `#x` |
| `nullable-sugar` | a type written `?T` rather than `T\|null` |
| `group` | a Python `except*` |
| `step` | an expression in a `for` loop's step list |

## Types

A type is an object, so its structure survives and nothing has to re-parse a string. It also keeps its
canonical `text`, because detectors match that text directly (a C#
`global::System.Collections.Generic.Dictionary<` prefix, for example).

| key | when | what |
|---|---|---|
| `text` | always | the language's canonical spelling, fully qualified where it names a class: `global::System.String?`, `str \| None`, `?\Shop\Money`, `Ref<number>` |
| `kind` | always | `named`, `keyword`, `nullable`, `union`, `intersection`, `array`, `tuple`, `object`, `function`, `literal`, `opaque` |
| `name` | `named`, `keyword` | the fully qualified class name, or the keyword: `Shop\Money`, `shop.money.Money`, `int`, `string` |
| `args` | generics, arrays, `nullable` | the type arguments in order; an array's element; the `nullable` wrapper's inner type |
| `members` | `union`, `intersection`, `tuple` | the member types in order, as written |
| `fields` | `object` | `[{"name", "type", "optional"?}]`, an object type's fields |
| `nullable` | it admits null | `true`: a `?T`, a union with `null`/`None`, a nullable reference |
| `valueType` | the compiler says so | `true` for a value type (a C# struct, enum, or primitive) |
| `element` | a collection whose element type is known | the element type, from a generic, a docblock `list<X>`, or an attribute such as `#[DataCollectionOf(X::class)]` |
| `constructs` | the expression names a class | the class a call of it builds: Python `Money` in `Money.of(...)`, `str` in `str(x)` |
| `origin` | always | where the type came from: `written` (the source's own annotation), `docblock`, `attribute`, `inferred` (the bridge's own sound inference), `compiler` (Roslyn, mypy) or `checker` (an outside type checker such as `vue-tsc`) |

`opaque` is for a type the bridge does not model: a conditional, mapped or template-literal type, or
`keyof`. It keeps `text` exactly as written and nothing else, so the type is never lost or misread.

## Symbols

A **symbol id** is one string per declaration, so that a call, a name and a doc reference each join to
the declaration they mean by plain string equality. The engine keeps no second index. Each language
spells ids its own way, and the spelling is fixed:

| language | a type | a member | a function |
|---|---|---|---|
| php | `Shop\Cart` | `Shop\Cart::add()`, `Shop\Cart::$items`, `Shop\Cart::MAX` | `Shop\total()` |
| python | `shop.cart.Cart` | `shop.cart.Cart.add` | `shop.cart.total` |
| csharp | `global::Shop.Cart` | `global::Shop.Cart.Add(global::System.Int32)` (Roslyn's display format, as the bridge writes `symbol` today) | — |
| typescript, vue | `<path>#Cart` | `<path>#Cart.add` | `<path>#total` (`<path>` the declaring file's absolute path) |

A **target** is what a call or construction reaches:

| key | when | what |
|---|---|---|
| `symbol` | always | the symbol id of the declaration called, spelled exactly as that declaration's own `symbol` |
| `type` | a member | the containing type's symbol id |
| `name` | always | the member's or function's name |
| `parameters` | the language types its parameters | the parameter types' `text`, in order |

## Comments

Comments are a list on the file, not nodes, and each one is attached to the node it documents by id.

| key | when | what |
|---|---|---|
| `id` | always | its position in the file's `comments` list |
| `kind` | always | `line` (`//`, `#`), `block` (`/* */`), `doc` (`/** */`, `///`, a docstring) or `markup` (`<!-- -->`) |
| `text` | always | the full text as written, markers included |
| `span` | always | `[start, end, line]`, as a node's |
| `attached` | it leads or trails a node | the id of that node: the declaration a docblock precedes, the statement a comment sits above |
| `trailing` | it sits after code on the same line | `true` |
| `refs` | a doc comment that names code | `[{"text", "symbol"?, "owner"?, "ownedHere"?, "blind"?}]`: each reference as written (`<see cref>`, `{@see}`, `@see`, Sphinx `:class:`), what it resolves to, and, when the whole reference does not resolve, the longest qualifier that does (`owner`), whether that owner is declared in the scanned code (`ownedHere`), and whether the compiler could not have resolved it anyway (`blind`) |

A Python docstring is both: the string-literal statement stays in the tree, and it is listed here as a
`doc` comment attached to the function or class it documents. That way a documentation rule reads every
language through this one list.

## The program line

| key | what |
|---|---|
| `symbols` | declarations outside the scanned files that the files reference: `[{"symbol", "kind", "name", "extends"?, "implements"?, "uses"?, "final"?, "abstract"?, "members"?}]`. A PHP bridge reflects them from the installed vendor packages, and a C# bridge reads them from the referenced assemblies. The engine cannot reach either, so without these a hierarchy stops at the edge of the scan |
| `packages` | the folders the language treats as packages (Python: folders holding `__init__.py`), absolute |
| `projects` | C#: `[{"path", "test"}]`, each project file and whether it is a test project |
| `aliases` | TypeScript and Vue: `[{"prefix", "path"}]`, the module path aliases the bridge resolved imports through |

A hierarchy inside the scanned files is not listed here. The engine builds it from the nodes' own
`extends` children, `refers` and `symbol`.

## Extras

Some facts exist in only one language. They go in `extras`, under the language's key, and each
language's keys are closed and typed in the schema, just as the generic ones are.

| language | key | on | what |
|---|---|---|---|
| php | `resolvedName` | a `Name` | the name as `NameResolver` resolved it, when it differs from `name` |
| php | `special` | a `Name` | `self`, `static` or `parent` |
| csharp | `forgivesNull` | a `SuppressNullableWarningExpression` | the `!`'s operand is declared nullable |
| csharp | `code` | a comment | the comment parses as one C# statement |
| python | `operators` | a `Compare` | the chained comparison's operators, in order (`a < b <= c` → `["<", "<="]`) |
| python | `level` | an `Import` | the relative-import dot count |
| vue | `directive` | a `Directive` | `{"name", "arg"?, "dynamicArg"?, "modifiers"}`: `v-model:title.lazy` is `{"name": "model", "arg": "title", "modifiers": ["lazy"]}` |
| vue | `component` | an `Element` | `true` when the tag names a component |
| vue | `block` | a `Block` | `{"tag", "lang"?, "setup"?, "scoped"?}` |
| vue | `aliases` | a `ForHead` | the `v-for` aliases, in order |
| typescript | `typeOnly` | an import | an `import type` |

## Languages

`language` is one of `php`, `vue`, `typescript`, `python`, `csharp`. Each writes `kind` in a fixed
vocabulary:

| language | `kind` vocabulary | `field` |
|---|---|---|
| php | php-parser's `getType()` (`Stmt_Class`, `Expr_MethodCall`, `Scalar_String`, `Name_FullyQualified`) | php-parser's sub-node names (`getSubNodeNames()`), always written, because the structural hash reads them |
| csharp | Roslyn's `SyntaxKind` name (`MethodDeclaration`, `InvocationExpression`) | Roslyn's property name for the slot, where the bridge writes it |
| python | the engine's own node and expression kinds (`FunctionDef`, `ClassDef`, `Call`, `Attribute`, `Compare`), listed in `inventory/python.md` | always written |
| typescript | the engine's own node and expression kinds (`ClassDecl`, `VariableDecl`, `member`, `call`, `NamedType`), listed in `inventory/frontend.md` | always written |
| vue | `Component` (the file root), `Block`, `Element`, `Text`, `Interpolation`, `Attribute`, `Directive`, `ForHead`; each `<script>` block's single child is a TypeScript `Module`, and every template expression is a TypeScript expression | always written |

**Every span is file-absolute.** A template expression's span points into the `.vue` file, not into the
attribute value it sits in, and a script block's nodes point into the `.vue` file as well. The bridge
adds the offsets once, so no reader ever has to.
