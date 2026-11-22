# Coverage: what each bridge does not translate yet

Assembled from `contract/coverage/<language>.md`, one file per bridge; change those, not this. A construct
listed here is written as `unsupported` (its sub-expressions still read) or translated with the loss its row
names, so a rule over it reports its coverage as incomplete instead of reporting nothing.

## PHP: what the bridge does not translate yet

Each row is a construct the PHP bridge writes as `unsupported` (with its sub-expressions as `parts`, so the names
and calls inside are still read) or translates with a loss, and why.

| Construct | Written as | Why |
|---|---|---|
| `declare(strict_types=1)` | `unsupported`, construct `declare` | version 5 has no kind for a compiler directive |
| `__halt_compiler()`, inline HTML | `unsupported` | no kind; nothing after it is code |
| `list()` and `[...]` as an assignment target | `collection` form `array` | version 5 writes a destructuring target as a `pattern`; not translated yet |
| `echo`, `print`, `unset`, `isset`, `empty`, `exit`, `clone`, `eval`, `include` | `call` whose callee is a `reference` named for the keyword | the callee carries no `refers` to PHP's own symbol yet |
| a cast `(int) $x` | `cast` whose `targetType` is a keyword `type-expression` | the written type's name is not stated |
| a parse error's partial declaration | the statements php-parser recovered | an `incomplete` node for the part it dropped is not written yet |

## Python: what the bridge does not translate yet

Each row is a construct the Python bridge writes as `unsupported` (with its sub-expressions as `parts`, so the names
and calls inside are still read) or translates with a loss, and why.

| Construct | Written as | Why |
|---|---|---|
| `assert` | `unsupported`, construct `assert` | version 5 has no kind for an assertion statement |
| a slice `a[1:2]` | `index` whose key is `unsupported`, construct `slice` | version 5 has no kind for a slice |
| `type X = ...` (3.12) | `unsupported`, construct `typealias` | not translated to `alias` yet |
| `//` floor division | `binary` with operator `divide` | version 5 has no floor-division operator |
| `@` matrix multiplication | `binary` with operator `multiply` | version 5 has no matrix-multiplication operator |
| a field's `written` type structure | absent | the bridge writes each annotation as a `type-expression` tree; the `written` fact is not filled |

## C#: what the bridge does not translate yet

Each row is a construct the C# bridge writes as `unsupported` (with its sub-expressions as `parts`, so the names
and calls inside are still read) or translates with a loss, and why.

| Construct | Written as | Why |
|---|---|---|
| a delegate declaration | `unsupported`, construct `DelegateDeclaration` | version 5 has no kind for a delegate type; `alias` would misstate it |
| `fixed`, `stackalloc`, `checked(...)`, `sizeof`, `typeof`, `nameof` as written | `unsupported` | no kind; the names inside are still read |
| a query expression (`from … select`) | `unsupported` | no kind for a query |
| a range `a..b`, an index from the end `^1` | `unsupported` | no kind for a range |
| `x is` with a recursive, relational or list pattern | `unsupported`, construct `is-pattern` | only `is null`, `is not null`, `is T` and `is T t` are translated |
| a using declaration `using var x = …;` | `variable` form `using` | its release at the end of the scope is not a `resource-scope` yet |
| an indexer's parameters | absent | the indexer is a `field` named `this`; its parameters are not written |
| a type parameter's constraints | absent | `where T : …` clauses are not joined to their `type-parameter` |
| generic arguments on a called name (`Make<int>()`) | absent | the callee `reference` keeps the name only |
| a node's `resolvedType` | absent | the file's `types` table is not written yet; `target` and `constantValue` are |
| an overload left ambiguous | no `target` | the ambiguity itself is not recorded |

## TypeScript: what the bridge does not translate yet

Each row is a construct the TypeScript bridge writes as `unsupported` (with its sub-expressions as `parts`, so the
names and calls inside are still read) or translates with a loss, and why.

| Construct | Written as | Why |
|---|---|---|
| `export { a }`, `export * from`, `export default <expression>`, `import x = require()` | `unsupported` | version 5 has no kind for a re-export; an `import` names what comes in, not what goes out |
| a comma expression `a, b` | `unsupported`, construct `comma` | no kind for a sequence of expressions |
| a tagged template, a regular expression, JSX | `unsupported` | no kind; the names inside are still read |
| a class static block, an index signature | `unsupported`, construct `member` | no kind for either |
| an object type literal, a mapped, conditional, indexed or `typeof` type | `type-expression` form `opaque` | its structure is in the resolved type, not the written one |
| `catch (e: unknown)` | `handler` form `catch-all`, the written type left out | a TypeScript catch catches everything; the annotation filters nothing |
| a selected overload | `target` names the shared symbol of the overload set | the signature chosen among overloads is not told apart yet |
| a namespace `namespace X {}` | `type` form `object` | version 5's `module` is a file's own scope |

## Vue: what the bridge does not translate yet

A Vue file is a module per `<script>` block, translated by the TypeScript bridge (see typescript.md), and a module
named `template` for the markup. Each row is a construct of the markup the bridge leaves out or translates with a
loss, and why.

| Construct | Written as | Why |
|---|---|---|
| `<script src>`, `<template lang="pug">` | left out | the bridge reads only inline TypeScript and HTML templates |
| `<style>` and custom blocks | left out | no kind; nothing in them is code a rule judges |
| a `v-for` destructuring alias | `pattern` under `bindings` | as written; its parts bind through the pattern |
| an event handler written as statements | a `block` under `value` | a directive's `value` holds one expression |
| a component tag's `form` | `component` from the Vue compiler's tag type | `<component :is>` resolves no file |
| a template name's `refers` (`{{ count }}` to the setup binding or prop it reads) | absent | template expressions are parsed on their own, with no checker; the name is matched to the script by name only |
| a `v-if`, `v-else-if`, `v-else` chain | sibling `element`s, each with its own `directive` | the chain is read from the siblings' order; no node joins them |

## Go: what the bridge does not translate yet

Each row is a construct the Go bridge writes as `unsupported` (with its sub-expressions as `parts`, so the names
and calls inside are still read) or translates with a loss, and why.

| Construct | Written as | Why |
|---|---|---|
| `go f()` | `unsupported`, construct `go` | version 5 has no kind for starting a goroutine |
| `ch <- v` | `unsupported`, construct `send` | no kind for a send; a receive is `unary` operator `receive` |
| `fallthrough` | `unsupported`, construct `fallthrough`, and the arm's `fallsThrough` | the arm says it; the statement has no kind of its own |
| a slice expression `a[i:j]`, a generic call `f[int](x)` | `unsupported` | no kind for a slice; a generic call's type arguments are not joined to the call |
| a switch's initializer `switch x := f(); x {` | absent | `dispatch` has no initializer role |
| `&^` | `binary` operator `bit-and-not` | the operator is not in the contract's list |
| a defined type that is not a struct or interface (`type Celsius float64`) | `alias` with its `underlyingType` | version 5's `type` forms are struct and interface for Go |
| `satisfies` across files | links only to interfaces declared in the same file | a link points at a node of the file it stands in |
| a struct literal of a named type | `collection` form `object` | it builds a value, not through a constructor |
