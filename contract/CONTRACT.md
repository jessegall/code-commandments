# The generic tree contract

Every language bridge — PHP, Vue, TypeScript, Python, C#, Go — answers in this one shape, and the engine
reads only this shape. A bridge is a small parser: it parses its language, and it writes what only that
language's own compiler or checker can know. Everything else the engine derives itself, into the same
fields, once it has read the stream. *Who fills it* says which is which. Version 4; a version 3 stream is the same contract without the type kinds
`pointer` and `map`, a version 2 stream is version 3 without a target's `parameters`, a version 1 stream is
version 2 without the Python extras `as`, `names` and `code` and the flag `elif`, and a reader of version 4
reads every one of them.

What the engine must be able to read is listed per engine in [`inventory/`](inventory/). The mapping
from each of those facts to a field here is in [`COVERAGE.md`](COVERAGE.md). The machine-checked form
is [`tree.schema.json`](tree.schema.json), and package `contract` decodes and validates a stream
against it.

## The stream

`<bridge> <path>...` writes JSON lines to stdout: one object per line, so a reader holds one file at a
time however large the project. Each line has exactly one key, and that key says what the line is:

```json
{"header": {"contract": "tree", "version": 2, "language": "php", "bridge": {"name": "php-bridge", "version": "4.374.0"}, "roots": ["/abs/src"]}}
{"file": {"path": "/abs/src/Cart.php", "language": "php", "errors": 0, "root": {"id": 0, "kind": "Stmt_Namespace", "role": "statement", "span": [6, 812, 3]}, "comments": []}}
{"program": {"symbols": []}}
{"trailer": {"files": 1, "resolution": {"expressions": 9992, "typed": 7823, "unjoined": 0}}}
```

1. **`header`**, always first. `contract` is always `"tree"`. `version` is the contract version. A reader
   names the versions it reads and refuses any other, so every change to this document raises it.
   `language` is the bridge's language (see *Languages*); a Vue bridge writes `vue`, with its script
   blocks' TypeScript trees inside each file. `bridge` names the program that wrote the stream.
   `roots` are the paths it was asked for, absolute and with symbolic links resolved.
2. **`file`**, one per source file, in any order.
3. **`program`**, at most one, after every `file`: the facts about the whole program that no file
   carries and the engine cannot reach (see *The program line*).
4. **`trailer`**, always last. `files` counts the `file` lines. A stream without a trailer was cut
   short, and a reader refuses it. `resolution` says how much the bridge's resolver saw and resolved:
   `expressions` and `typed` count the expressions it saw and typed, `calls` and `resolved` the calls
   and constructions it saw and bound, and `unjoined` the facts its checker produced that matched no
   node (mypy's per-span types, joined inside the bridge). A drift in spans shows there, never in
   silence. Each count is optional, and a bridge writes the ones it has.

One engine run reads one stream per language. Their symbol spaces are separate: an id means something
only beside its stream's `language`. A rule that crosses languages, such as a TypeScript type that
mirrors a PHP Data class, joins the two streams in the engine by what both trees already say: the
class's `#[TypeScript]` attribute and the fields each side declares.

`--serve` keeps the bridge running: it answers each request on stdin, one JSON line (`{"paths": [...],
"write": [...]}`, as the mypy bridge takes it today), with a full stream, header to trailer. A request
may carry `"contents"`, absolute paths to the text a rewrite has drafted for them, and the bridge reads
those in place of the disk, a file under a requested folder that exists only there included. A bridge
that fails as a whole exits non-zero and writes why to stderr. A file it could only partly parse is
still written, with `errors` counting what it could not read.

**Strict.** Every object in this contract is closed: a key that is not documented here is an error,
never ignored. That includes the per-language `extras` (see *Extras*), which are closed per language
too. A field *Who fills it* gives to the engine is an error in a stream. A new fact means a new version
of this document.

**Absent, never guessed.** A fact the resolver could not resolve is left out. It is never `null`, and
never a best guess. `null` appears only as a literal's own `value`. A file's `resolver` says whether a
resolver ran on it at all, so an absent fact always reads as "could not", never as "did not try".

## Who fills it

| fact | php | python | csharp | typescript, vue | go |
|---|---|---|---|---|---|
| syntax: `kind` … `flags`, `declared`, `returns`, comments | bridge | bridge | bridge | bridge | bridge |
| names in `declared`/`returns` resolved to a class | bridge (NameResolver) | engine | bridge (Roslyn) | bridge (checker) | bridge (go/types) |
| `resolved` | engine | bridge (mypy) | bridge (Roslyn) | bridge (checker) | bridge (go/types) |
| `symbol` | bridge | bridge | bridge | bridge | bridge |
| `refers` | bridge (NameResolver) | engine | bridge (Roslyn) | bridge (checker) | bridge (go/types) |
| `target` | engine | engine | bridge (Roslyn) | bridge (checker) | bridge (go/types) |
| `resolves` | — | engine | — | bridge | — |
| `constant`, `inherited` | engine | engine | bridge (Roslyn) | engine | bridge (go/types) for `constant`, — for `inherited` |
| `fills-outside` | — | — | bridge (Roslyn) | — | — |
| comment `refs` resolution | engine | engine | bridge (Roslyn) | engine | — |
| docblock types (`@var`, `@param`, `list<X>`) | engine, from the comment's text and the file's `use` nodes | engine | — | engine | — |
| `program.symbols` | bridge (reflection over vendor) | — | bridge (referenced assemblies) | — | — |
| `program.packages` | — | bridge | — | — | — |
| `program.aliases` | — | — | — | bridge | — |
| `file.test` | — | — | bridge (`IsTestProject`) | — | — |

"engine" means the field never appears in the stream: the engine computes it after reading, into the
same field, so a detector reads it the same way whoever filled it. "—" means the language has no
such fact.

## A file

| key | when | what |
|---|---|---|
| `path` | always | absolute, with symbolic links resolved |
| `language` | always | the language this file's `root` is written in |
| `errors` | always | syntax errors in the file: `0` for a file parsed whole |
| `context` | the file informs but is not judged | `true`: a file outside `write` that the engine still needs to resolve types, targets and ancestry into. It is never reported on |
| `test` | the bridge knows it | `true` for a file of a test project |
| `module` | the language names modules | the dotted module the file is (Python `shop.cart`) |
| `resolver` | a bridge resolver exists for the language | `{"tool", "ran"}`: the resolver (`roslyn`, `mypy`, `tsc`, `go/types`) and whether it ran on this file |
| `root` | always | the file's root node |
| `comments` | always | every comment in the file, in source order (see *Comments*) |
| `types` | a node names its type by `resolvedType` | the distinct types the file's nodes resolve to, each said once: a type thousands of expressions share is written one time, and the reader puts it back on each node |

The engine reads the file's bytes itself: `path` is where they are, and every span indexes into them.
A node's source text is the bytes of its span, so no node repeats it.

## A node

Every syntax node, nested as the language nests them. Tokens, whitespace and comments are not nodes.

| key | when | what |
|---|---|---|
| `id` | always | the node's number, unique within its file: its position in a pre-order walk, the root `0` |
| `kind` | always | the language's own name for the node (see *Languages*): `Expr_MethodCall`, `InvocationExpression`, `Call` |
| `role` | always | `statement`, `expression`, `member` (any declaration of a type, function, method, property, field, constant or enum case, at any level), `type` (a type as written), `pattern`, `markup` (a template element, attribute or text) or `other` |
| `is` | the node answers any | the language-neutral questions it answers yes to (see *Neutral kinds*) |
| `span` | always | `[start, end, line]`: `[start, end)` in UTF-8 bytes into the file, a byte order mark counted, trivia excluded; `line` the 1-based line `start` is on |
| `field` | always, but on the root | the slot this node fills in its parent: `var`, `args`, `test`, `body`. A list slot repeats the same field on each item, in order |
| `children` | it has any | its child nodes, in source order |
| `name` | declarations, names, identifiers, members, elements, attributes | the name as written (`add`, `Cart`, `self`, `div`); never empty, so a key written as `""` and a name a syntax error left missing carry none |
| `literal` | literals | `string`, `int`, `float`, `bool`, `null`, `undefined`, `bytes`, `ellipsis`, `interpolated`, `format` |
| `value` | literals the language folds, static attributes | the decoded value: a string without quotes or escapes, `true`/`false`, `null`. An `int` or `float` is a decimal string, so no width is lost |
| `operator` | binary, unary, assignment, update expressions; a TypeScript heritage clause | the operator token: `==`, `??`, `+=`, `!`, `not`, `await`, `instanceof`; a heritage clause's keyword, `extends` or `implements` |
| `modifiers` | the node carries any | the modifier keywords written on it: `public`, `static`, `readonly`, `final`, `abstract`, `override`, `async`, `const`, `partial`, `out`. In source order where the parser keeps it, otherwise the language's own order. A language's implicit default (TS `public`) is not written |
| `flags` | the node has any | syntax facts that are not modifier keywords (see *Flags*) |
| `declared` | declarations with a written type; a catch clause | the type the source declares for a parameter, property, field or variable, or the type a catch catches (a union when it catches several) (see *Types*) |
| `returns` | function-likes with a written return type | the declared return type |
| `resolved` | expressions the resolver typed | the type the compiler or checker gives the expression |
| `resolvedType` | in place of `resolved` | the index in the file's `types` of that type; a reader treats it as `resolved` |
| `symbol` | declarations | the declaration's symbol id (see *Symbols*) |
| `refers` | names and imports that resolve | the symbol id the name names — a class name its class, an import binding what it imports — whether or not that declaration is in the scan |
| `target` | calls and constructions that resolve | the declaration called (see *Symbols*) |
| `resolves` | imports and component tags that resolve | the absolute path of the file the import or tag reaches |
| `constant` | expressions with a compile-time value | `true`: a literal, an enum case, a constant, or arithmetic on them, as the compiler folds it |
| `inherited` | members that override or implement another | `true`, decided against the whole hierarchy, not by an `override` keyword |
| `fills-outside` | members with `inherited` | `true` when every declaration the member fills was written outside this solution, so its signature is not the project's to change. A sibling project's interface compiles from source and is the project's own; a package's or the framework's arrives as metadata. A reader cannot tell these apart, since the scan may hold neither |
| `extras` | the language has facts of its own on it | `{"<language>": {...}}`, closed per language (see *Extras*) |

A node carries no parent. Its parent is the node whose `children` hold it, and a reader builds the
parent links once as it reads the file, because nearly every question a detector asks climbs them.

A written type is both: the `role: type` child nodes are its syntax, with spans, and `declared` or
`returns` is the same type read as structure. The bridge writes both from the one parse.

### Neutral kinds

`kind` is the language's own vocabulary, so a detector written for one language reads that language's
kinds. A rule that holds for every language asks `is` instead. It is a list drawn from this closed set:

| value | the node is |
|---|---|
| `function` | a function-like with a body: function, method, constructor, accessor, closure, arrow, lambda |
| `type-declaration` | a declaration of a type: class, interface, trait, enum, struct, record, type alias |
| `parameter` | a function's parameter |
| `block` | a statement list |
| `branch` | a branching construct: if, else-if, switch, match, ternary, conditional, Go's select and type switch |
| `loop` | a loop: for, foreach, for-in/of, while, do |
| `return` | a return |
| `throw` | a throw or raise |
| `bail-out` | a statement that leaves its block: return, throw, break, continue |
| `expression-statement` | an expression used as a statement |
| `call` | a call: function, method, static |
| `construction` | a construction: `new`, object creation, a call of a class |
| `member-access` | a property or member read: `->x`, `.x`, `::x` |
| `null-safe` | a null-safe access: `?->`, `?.` |
| `self-reference` | `$this`, `this`, `self` |
| `identifier` | a bare name read |
| `assignment` | an assignment, compound or not |
| `comparison` | an equality, identity or ordering comparison |
| `conjunction` | a logical and or or: `&&`, `||`, `and`, `or` |
| `literal` | a literal |
| `import` | an import or use |
| `catch` | a catch or except clause |

### Flags

A list drawn from this closed set. Each appears only where the language has the construct.

| value | on |
|---|---|
| `variadic` | a parameter that collects the rest: `...$x`, `*args`, `params` |
| `keywords` | a parameter that collects keyword arguments: `**kwargs` |
| `by-ref` | a by-reference parameter or argument |
| `promoted` | a PHP constructor parameter promoted to a property |
| `keyword-only` | a Python parameter after `*` |
| `optional` | a field or parameter that may be missing: TS `x?`. A default value is a `default` child, not this flag |
| `spread` | an unpacked argument or element: `...$x`, `*x`, `**x` |
| `named` | an argument passed by name |
| `async` | an async function, loop, `with` or comprehension |
| `generator` | a function that yields |
| `short-ternary` | a PHP `?:` |
| `shorthand` | a Vue directive written `:x`, `@x` or `#x` |
| `component` | a Vue template element its compiler resolves to a component, not a native tag: `<Foo>`, `<my-widget>`, `<component :is>`, `<transition>` |
| `nullable-sugar` | a type written `?T` rather than `T\|null` |
| `group` | a Python `except*` |
| `step` | an expression in a `for` loop's step list |
| `elif` | a Python `if` written as the `elif` of the one before it, rather than an `if` inside an `else` |

## Types

A type is an object, so its structure survives and nothing has to re-parse a string. It also keeps its
canonical `text`, because detectors match that text directly (a C#
`global::System.Collections.Generic.Dictionary<` prefix, for example).

| key | when | what |
|---|---|---|
| `text` | always | the language's canonical spelling, fully qualified where it names a class: `global::System.String?`, `str \| None`, `?\Shop\Money`, `Ref<number>` |
| `kind` | always | `named`, `keyword`, `parameter` (a type parameter such as `T`), `union`, `intersection`, `array`, `tuple`, `object`, `function`, `literal`, `pointer`, `map`, `opaque` |
| `name` | `named`, `keyword`, `parameter` | the fully qualified class name, the keyword, or the parameter's name: `Shop\Money`, `shop.money.Money`, `int`, `T` |
| `args` | generics, arrays | the type arguments in order; an array's element |
| `members` | `union`, `intersection`, `tuple` | the member types in order, as written |
| `fields` | `object` | `[{"name", "type", "optional"?}]`, an object type's fields, each named by its key as written, which may be `""` |
| `parameters` | `function` | the parameter types in order |
| `returns` | `function` | the return type |
| `value` | `literal` | the literal's value, decoded as a node's `value` is |
| `nullable` | it admits absence | `true`: `?T`, a union with `null`, `None` or `undefined`, a C# nullable reference. This flag is the one place absence is read; the members show which kind of absence |
| `valueType` | the compiler says so | `true` for a value type (a C# struct, enum, or primitive) |
| `element` | a collection whose element type is known | the element type, from a generic, a docblock `list<X>`, or an attribute such as `#[DataCollectionOf(X::class)]` |
| `args` | generics, arrays, maps | for a map, its key and then its value |
| `constructs` | the expression names a class | the class a call of it builds: Python `Money` in `Money.of(...)`, `str` in `str(x)` |
| `origin` | always | where the type came from: `written` (the source's own annotation), `docblock`, `attribute`, `inferred` (the engine's own sound inference), `compiler` (Roslyn, mypy, tsc) |

A `pointer` carries what it points at as its `element`, and a `map` its value, with its key and value in `args`:
both are kinds of their own because a rule reads them structurally, where reading a `*` or a `map[` off the
text would be parsing a type again. `?T` is the type `T` with `nullable: true`; the node's `nullable-sugar` flag says it was written that
way. `opaque` is for a type the bridge does not model: a conditional, mapped or template-literal type,
or `keyof`, or a resolved type with no single class. It keeps `text` exactly as written and no
structure (`name`, `args`, `members`, `fields`, `parameters`, `returns`, `value`, `element`), but the facts the
resolver knows about it stay: `nullable`, `valueType`, `constructs`. So the type is never lost or misread.

## Symbols

A **symbol id** is one string that names a declaration, so that a call, a name and a doc reference
each join to the declaration they mean by string equality. Each language spells ids its own way, and
the spelling is fixed:

| language | a type | a member | a function |
|---|---|---|---|
| php | `Shop\Cart` | `Shop\Cart::add()`, `Shop\Cart::$items`, `Shop\Cart::MAX` | `Shop\total()` |
| python | `shop.cart.Cart` | `shop.cart.Cart.add` | `shop.cart.total` |
| csharp | `global::Shop.Cart` | `global::Shop.Cart.Add(global::System.Int32)` | a local function: its member's id, `.`, its name |
| go | `example.com/shop/cart.Cart` | `example.com/shop/cart.Cart.Add`, a field `example.com/shop/cart.Cart.lines` | `example.com/shop/cart.Total` |
| typescript, vue | `<path>#Cart` | `<path>#Cart.add`, a namespace member `<path>#Ns.f` | `<path>#total`, a default export `<path>#default` |

- **One id may name several declarations**: a C# partial class, an overload set in TS, a Python
  `@overload` or a `@property` with its `.setter`, a merged TS declaration. The engine keeps ids as a
  multimap.
- **C# spells the original definition**: a call on `Box<int>.Add` or `M<int>(5)` names `Box<T>.Add`
  and `M<T>`, as Roslyn's `OriginalDefinition` prints them, so it equals the declaration's id.
- **PHP class, function and method names compare case-insensitively**, as PHP resolves them. The id is
  spelled as the declaration spells it, and a `$property` or constant compares exactly.
- **A TS id splits at its last `#`**, so a path holding one stays readable. A declaration of the
  standard library has no path: it is its global name, `Array.map`, `fetch`, `ErrorConstructor.new`
  for a construct signature.

A **target** is the declaration static lookup finds for a call or construction: the member the
receiver's static type declares or inherits, nearest first. The receiver's own type is its child's
`resolved`, so a detector that needs the receiver reads it there.

| key | when | what |
|---|---|---|
| `symbol` | always | the symbol id of the declaration called |
| `type` | a member | the id of the type that declares it |
| `name` | always | the member's or function's name |
| `parameters` | the bridge knows the bound method | the types of the parameters the call fills, in order, as bound at the call: a generic's arguments filled in, an extension method called on a receiver without its `this`; each spelled as a `resolved` type's `text` |

## Comments

Comments are a list on the file, not nodes. Each one names the node it belongs to.

| key | when | what |
|---|---|---|
| `id` | always | its position in the file's `comments` list |
| `kind` | always | `line` (`//`, `#`), `block` (`/* */`), `doc` (`/** */`, `///`, a docstring) or `markup` (`<!-- -->`) |
| `text` | always | the full text as written, markers included |
| `span` | always | `[start, end, line]`, as a node's |
| `attached` | it leads or trails a node | the id of that node (see below) |
| `trailing` | it sits after code on the same line | `true` |
| `refs` | a doc comment that names code | `[{"text", "symbol"?, "owner"?, "ownedHere"?, "blind"?}]`: each reference as written (`<see cref>`, `{@see}`, `@see`, Sphinx `:class:`), what it resolves to, and, when the whole reference does not resolve, the longest qualifier that does (`owner`), whether that owner is declared in the scanned code (`ownedHere`), and whether the compiler could not have resolved it anyway (`blind`) |
| `extras` | the language has facts of its own on it | as a node's |

**Attachment.** A leading comment belongs to the outermost node that starts at the first token after
it, any comments in between skipped: the method, not the attribute or the name inside it; the `Stmt_Expression`, not the assignment it
wraps. A trailing comment belongs to the outermost node that ends on its line before it. A comment with
code on neither side belongs to nothing. A node's comments are every comment whose `attached` is its
id, so a node reads all of them, not only the last docblock.

A Python docstring is both: the string-literal statement stays in the tree, and it is listed here as a
`doc` comment attached to the function or class it documents. That way a documentation rule reads every
language through this one list.

## The program line

| key | what |
|---|---|
| `symbols` | declarations outside the scanned files (see below). A PHP bridge reflects them from the installed vendor packages, a C# bridge reads them from the referenced assemblies. The engine can reach neither, so without them a hierarchy and a call chain stop at the edge of the scan |
| `unreadable` | `[{"symbol", "reason"}]`: classes the scan names that the scanned project's own loader failed on (a PHP autoloader that throws), and its message. Each stays outside the scan — a hierarchy or a chain through it stops there — and the tool says so, rather than dropping it unseen |
| `loader` | why the scanned project's own loader was not used, absent when it was: it is not there (a composer.json naming dependencies with no `vendor/autoload.php`), or it does not load here, with the failure. No class of a package is then read, so a method a package's interface fixes reads as the class's own and a rule that would excuse it cannot — the tool says so rather than letting the finding look like the rule's mistake |
| `packages` | the folders the language treats as packages (Python: folders holding `__init__.py`), absolute |
| `aliases` | TypeScript and Vue: `[{"prefix", "path"}]`, the module path aliases the bridge resolved imports through |

An outside declaration is `{"symbol", "kind", "name", "extends"?, "implements"?, "uses"?, "modifiers"?,
"members"?}`. `kind` is `class`, `interface`, `trait`, `enum` or `struct`; `extends`, `implements` and
`uses` are symbol ids; `members` are `[{"symbol", "name", "kind", "modifiers"?, "declared"?,
"returns"?, "documented"?, "parameters"?}]`, where `kind` is `method`, `property`, `field` or
`constant`, `documented` is the docblock return type when it says more than the native one, and each
parameter is `{"name", "declared"?, "flags"?}`. The set is closed: every ancestor of an outside
declaration, and every class an outside member's type names, is in it too, so a chain such as
`$a->foo()->bar()` resolves as far as the types reach.

A hierarchy inside the scanned files is not listed here. The engine builds it from the nodes.

## Extras

Some facts exist in only one language. They go in `extras`, under the language's key, and each
language's keys are closed and typed in the schema, just as the generic ones are.

| language | key | on | what |
|---|---|---|---|
| csharp | `forgivesNull` | a `SuppressNullableWarningExpression` | the `!`'s operand is declared nullable |
| csharp | `code` | a comment | the comment parses as one C# statement |
| php | `code` | a line comment | its text, marker and trailing `,`/`;` stripped, parses as PHP inside `[…]` |
| python | `code` | a `#` comment | the words after the `#` parse as one Python statement, end to end, that is not a lone name |
| python | `operators` | a `Compare` | the chained comparison's operators, in order (`a < b <= c` → `["<", "<="]`) |
| python | `level` | an `ImportFrom` | the relative-import dot count |
| python | `as` | an `alias` | the name an import binds when it renames: `y` in `import x as y` |
| python | `names` | a `Global`, `Nonlocal` | the names it declares, in order |
| vue | `directive` | a `Directive` | `{"name", "modifiers"}`: `v-model:title.lazy` is `{"name": "model", "modifiers": ["lazy"]}`. The argument is the child in field `arg`: an `Identifier` for a static `title`, an expression for a dynamic `[key]` |
| typescript | `typeOnly` | an import | an `import type` |

## Languages

`language` is one of `php`, `vue`, `typescript`, `python`, `csharp`, `go`. Each writes `kind` and `field` in
its own parser's vocabulary, so a bridge never translates:

| language | `kind` | `field` |
|---|---|---|
| php | php-parser's `getType()`: `Stmt_Class`, `Expr_MethodCall`, `Scalar_String`, `Name_FullyQualified`; `File` for the file root | php-parser's sub-node names (`getSubNodeNames()`); `stmts` under the root |
| go | the name of the node's type in Go's `go/ast`: `FuncDecl`, `CallExpr`, `SelectorExpr`, `RangeStmt`, `ChanType`; `File` for the file root | the `ast` struct field the child fills, lowercased: `body`, `fun`, `args`, `recv`, `rhs` |
| csharp | Roslyn's `SyntaxKind`: `MethodDeclaration`, `InvocationExpression` | the Roslyn property the child is: `Expression`, `ArgumentList`, `Body` |
| python | the class name in Python's `ast`: `FunctionDef`, `ClassDef`, `Call`, `Attribute`, `Compare` | the `ast` field: `body`, `args`, `func`, `test` |
| typescript | TypeScript's `SyntaxKind`: `ClassDeclaration`, `CallExpression`, `TypeReference` | the compiler's property name: `expression`, `arguments`, `body` |
| vue | `Component` (the file root), `Block`, `Element`, `Text`, `Interpolation`, `Attribute`, `Directive` | `blocks`, `children`, `attributes`, `arg`, `value`, `alias`, `iterable` |

**Go.** The tree is read from `go/ast` by reflection over its own structs, so every node the language has is
written without the bridge being taught it: `kind` is the struct's name and `field` is the struct field the child
fills. A field holding a comment is no child, since comments live on the file, and `ast.File`'s own second list of
unresolved names is not read twice.

Go writes a type with the same nodes it writes an expression with, so `role: type` is given to a child filling a
`type` field and to everything below it. A method's receiver is the child in field `recv`, a field list like any
parameter list, which is how a method is bound to the type it is declared on: Go declares a method outside its
type, so nesting says nothing about what a method belongs to.

`true`, `false` and `nil` are predeclared identifiers in Go, not literals, so they are written as `Ident` and what
they mean is the checker's to say. A rune literal is an `int` whose value is its code point, and an imaginary
literal is written as a `float`. A declaration carries the `name` of the identifier it declares as well as the
identifier itself, except where it declares several at once (`a, b int`), which carries none.

A comment group is one comment: the run of `//` lines above a declaration is that declaration's one doc comment,
and its `kind` is `doc` only where the parser gave the group to a declaration as its documentation. A comment
meant to be read on its own, as a fixture's marker is, needs a blank line between it and what follows, which is
what leaves it a comment of its own rather than the next declaration's documentation. A node the
parser recovered from a syntax error can end before it starts; such a node's span ends where it begins, so no
reader is handed bytes the node does not stand for.

**What Go has and where the tree carries it.** Each row is a construct the other four languages have no field
for, and every one of them is carried because a rule reads it. A construct no rule reads is written nowhere.

| Go construct | where it is | the rule that reads it |
|---|---|---|
| a method's receiver | the child in field `recv`, a field list as any parameter list is | feature-envy, handed-out-collection, unread-collaborator: a method belongs to the type its receiver names, not to a nesting, so the type a method belongs to is the declaration that receiver's type names, which a reader joins by symbol id: `example.com/shop/cart.Cart.Total` belongs to `example.com/shop/cart.Cart` |
| an embedded field | a `Field` with no `name` and a `declared` type | coupled-fields: a field with no name of its own is an embedded one, and what it embeds is the type it declares |
| several results | `returns` is a `tuple` of them, each result a `Field` of the function type's `results` | the `(T, error)` pardon: a caller handling the error where it stands is Go's design, not a sin |
| a named result | that `Field` carries its own `name` and `declared` | the same pardon: a bare `return` with named results is not an empty answer |
| `select`, a type switch | the neutral `branch`, as every other choice is | deep-nesting, type-switch: a select is a choice the reader holds open, and a type switch asks what a value IS |
| `panic` | a `CallExpr` like any other; no node is a `throw` | nothing: Go has no throw to flag, and the exception rules were dropped for Go |
| a receiver's name | an ordinary `parameter`; no node is a `self-reference` | nothing: `self-reference` asks for `$this`, which Go has not |
| implicit interface satisfaction | nowhere | nothing reads it, so the bridge never computes it. `inherited` is absent for Go, and a rule that wanted it would have to ask `types.Implements` through a new fact |
| a pointer | a type of kind `pointer`, with what it points at as its `element` and `nullable: true` | phantom-nullable: a pointer field every read dereferences is a pointer that should be a value |
| a map | a type of kind `map`, its key and value in `args` and its value as its `element` | dict-bag, dict-return-bag, raw-decoded-return: a `map[string]any` read by keys written in the source is Go's bag |
| a channel and its direction | the `ChanType` node's `operator`, `chan`, `chan<-` or `<-chan`; the type itself is `opaque` with the text the compiler spells | nothing reads a channel's element, so it keeps no structure |
| a generic constraint | nowhere | nothing reads it |
| exported against unexported | the `name`'s own first letter | every rule about reach: in Go capitalisation IS the visibility, so the decorator reads the name and no `public` or `private` modifier is invented |
| `error` | a `named` type whose `name` is `error`, since the language itself declares it | every error rule and the `if err != nil` pardon, read through the type rather than by matching a name |
| `true`, `false`, `nil` | an `Ident` whose `refers` names the universe's own declaration | nothing yet; what they mean is the checker's, and a shadowed `nil` means something else |

**Go's types.** They come from `go/types`, with the module's own packages checked from the sources the run
already read and an empty package answered for anything outside the module, the standard library included. That
is a decision about what a consumer pays, and it is measured: typing a module's own sources gives 86 percent of
the expressions a full typing gives and 89 percent of its cross-package named types, in a fortieth of the time,
with no Go toolchain installed. What it loses is every type reached through an import outside the module, and
such a type is absent rather than guessed, so no rule reads one the checker never gave. A file's `resolver`
says `go/types` and whether it ran.

A type checker checks a package where a tree is read per file, so the files of a request are grouped into the
packages the compiler would compile them as, and a package is completed from its own folder: a request naming
one file is still typed against its whole package, since otherwise every name its siblings declare would read as
unresolved, and the files the request did not name are `context`. A file's `module` is the import path of the
package it belongs to. A file this platform does not build, by its `//go:build` line or its name's own suffixes, is
still written with its tree and carries no types, which is the honest answer rather than a silent gap: it is
judged by every rule that reads syntax, wherever it is read, and by the rules that read types only where the
platform it is written for builds it. A file a generator wrote, which Go marks with a line of its own before the
package clause, is read so that everything through it resolves and is never reported on.

`nullable` is the one place the contract reads absence, and for Go that is a pointer and an interface: a nil
pointer and a nil interface are a value that is missing, while a nil slice ranges cleanly and a nil map reads, so
neither is written as admitting absence. A pointer, a map and a channel have no kind of their own in *Types* and
are written `opaque`, which keeps the text the compiler spells and the facts it knows; `valueType` is true for a
basic type, a struct and an array, which Go copies. A function's `returns` is the one type it answers, or a
`tuple` of several, which is Go's own shape and where every `(T, error)` reading starts.

**Python.** A node whose `ast` class holds one name as a plain string writes it as `name`: a `FunctionDef`'s
or `ClassDef`'s own, an `arg`, a `Name`, an `Attribute`'s attribute, a named `keyword`, an `alias`'s imported
name, an `ImportFrom`'s module as written (none for `from . import x`), the name an `ExceptHandler` binds, a
`MatchAs` or `MatchStar` capture, and a `MatchMapping`'s `**rest`. A `FormattedValue`'s conversion is its `operator`: `!r` in `f"{x!r}"`.

**Vue.** A `Block` is a top-level `<template>`, `<script>`, `<style>` or custom block; its own
attributes (`setup`, `lang`, `generic`, `src`, `scoped`) are its `Attribute` children, and a
`<script>` block's content is one TypeScript `SourceFile` in field `children`. An `Attribute` carries
`name` and, for a static value, `value`. A `Directive` holds its argument in field `arg` and its value
as a TypeScript expression in field `value`. A `v-for` value is split into its `alias` patterns and
its `iterable`, and a `v-slot` value is a pattern, so destructuring survives.

**Every span is file-absolute.** A template expression's span points into the `.vue` file, not into the
attribute value it sits in, and a script block's nodes point into the `.vue` file as well. The bridge
adds the offsets once, so no reader ever has to.
