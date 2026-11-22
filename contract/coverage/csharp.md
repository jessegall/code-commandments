# C#: what the bridge does not translate yet

Each row is a construct the C# bridge writes as `unsupported` (with its sub-expressions as `parts`, so the names
and calls inside are still read) or translates with a loss, and why.

| Construct | Written as | Why |
|---|---|---|
| a delegate declaration | `unsupported`, construct `DelegateDeclaration` | version 5 has no kind for a delegate type; `alias` would misstate it |
| `fixed`, `stackalloc`, `checked(...)`, `sizeof`, `typeof`, `nameof` as written | `unsupported` | no kind; the names inside are still read |
| a query expression (`from … select`) | `unsupported` | no kind for a query |
| a range `a..b`, an index from the end `^1` | `unsupported` | no kind for a range |
| `x is` with a recursive, relational or list pattern | `unsupported`, construct `is-pattern` | `is null`, `is T`, `is T t` and an `is` over constants (`is "a" or "b"`, read as the comparisons it means) are translated |
| a using declaration `using var x = …;` | `variable` form `using` | its release at the end of the scope is not a `resource-scope` yet |
| an indexer's parameters | absent | the indexer is a `field` named `this`; its parameters are not written |
| a type parameter's constraints | absent | `where T : …` clauses are not joined to their `type-parameter` |
| generic arguments on a called name (`Make<int>()`) | absent | the callee `reference` keeps the name only |
| a node's `resolvedType` | absent | the file's `types` table is not written yet; `target` and `constantValue` are |
| an overload left ambiguous | no `target` | the ambiguity itself is not recorded |

## Not owed

| Item | Why |
|---|---|
| `fact/*/conversion` | `conversion` is the `!r` of Python's f-string. A C# `{x:F2}` format clause or `{x,10}` alignment is formatting, not a conversion of the value; if you read it as owed, the place is the `InterpolatedStringExpressionSyntax` arm of `Translated()`. |
| `fact/argument/spread` | C# has no call-site spread; `..` exists only inside a collection expression (an `entry`, proven by a sample below). |
| `fact/arm/fallsThrough` | C# forbids falling out of a `case`; stacked labels (`case 1: case 2:`) are one arm with two patterns. |
| `fact/break/depth` | PHP's `break 2`. |
| `fact/continue/depth` | PHP's `break 2`. |
| `fact/call/nullConditional` | `a?.M()` is a null-conditional `access` callee (written, `MemberBindingExpression`); a C# call has no `?()` form. |
| `fact/comprehension/form` | Python's. A C# query expression is a different construct and `contract/coverage/csharp.md` records it as unsupported. |
| `fact/comprehension-clause/async` | Python's. A C# query expression is a different construct and `contract/coverage/csharp.md` records it as unsupported. |
| `fact/comprehension-clause/form` | Python's. A C# query expression is a different construct and `contract/coverage/csharp.md` records it as unsupported. |
| `kind/comprehension` | Python's. A C# query expression is a different construct and `contract/coverage/csharp.md` records it as unsupported. |
| `kind/comprehension-clause` | Python's. A C# query expression is a different construct and `contract/coverage/csharp.md` records it as unsupported. |
| `role/comprehension/clauses` | Python's. A C# query expression is a different construct and `contract/coverage/csharp.md` records it as unsupported. |
| `role/comprehension/element` | Python's. A C# query expression is a different construct and `contract/coverage/csharp.md` records it as unsupported. |
| `role/comprehension/key` | Python's. A C# query expression is a different construct and `contract/coverage/csharp.md` records it as unsupported. |
| `role/comprehension/value` | Python's. A C# query expression is a different construct and `contract/coverage/csharp.md` records it as unsupported. |
| `role/comprehension-clause/bindings` | Python's. A C# query expression is a different construct and `contract/coverage/csharp.md` records it as unsupported. |
| `role/comprehension-clause/condition` | Python's. A C# query expression is a different construct and `contract/coverage/csharp.md` records it as unsupported. |
| `role/comprehension-clause/iterable` | Python's. A C# query expression is a different construct and `contract/coverage/csharp.md` records it as unsupported. |
| `fact/directive/shorthand` | Vue markup. A C# `[Attr]` is an `annotation`. |
| `fact/element/form` | Vue markup. A C# `[Attr]` is an `annotation`. |
| `fact/element/resolves` | Vue markup. A C# `[Attr]` is an `annotation`. |
| `fact/element/tag` | Vue markup. A C# `[Attr]` is an `annotation`. |
| `kind/attribute` | Vue markup. A C# `[Attr]` is an `annotation`. |
| `kind/directive` | Vue markup. A C# `[Attr]` is an `annotation`. |
| `kind/element` | Vue markup. A C# `[Attr]` is an `annotation`. |
| `kind/embedded-expression` | Vue markup. A C# `[Attr]` is an `annotation`. |
| `link/directive/body` | Vue markup. A C# `[Attr]` is an `annotation`. |
| `role/attribute/value` | Vue markup. A C# `[Attr]` is an `annotation`. |
| `role/directive/argument` | Vue markup. A C# `[Attr]` is an `annotation`. |
| `role/directive/bindings` | Vue markup. A C# `[Attr]` is an `annotation`. |
| `role/directive/iterable` | Vue markup. A C# `[Attr]` is an `annotation`. |
| `role/directive/pattern` | Vue markup. A C# `[Attr]` is an `annotation`. |
| `role/directive/value` | Vue markup. A C# `[Attr]` is an `annotation`. |
| `role/element/attributes` | Vue markup. A C# `[Attr]` is an `annotation`. |
| `role/element/children` | Vue markup. A C# `[Attr]` is an `annotation`. |
| `role/embedded-expression/expression` | Vue markup. A C# `[Attr]` is an `annotation`. |
| `fact/handler/group` | `except*` is Python's. |
| `fact/import/relativeLevel` | Python and TypeScript. |
| `fact/import/typeOnly` | Python and TypeScript. |
| `fact/import/resolves` | A `using` names a namespace, which spreads over many files; there is no one file it reaches. |
| `fact/parameter/evaluated` | A C# default is evaluated at the call (`evaluated: call`) and every parameter can be named (`position: either`): both documented defaults, so absent. `optional` is TypeScript's `x?`. |
| `fact/parameter/position` | A C# default is evaluated at the call (`evaluated: call`) and every parameter can be named (`position: either`): both documented defaults, so absent. `optional` is TypeScript's `x?`. |
| `fact/parameter/optional` | A C# default is evaluated at the call (`evaluated: call`) and every parameter can be named (`position: either`): both documented defaults, so absent. `optional` is TypeScript's `x?`. |
| `fact/return/namedResults` | Go. |
| `fact/scope-declaration/form` | Python/PHP `global` and `nonlocal`; C# has no such statement. |
| `kind/scope-declaration` | Python/PHP `global` and `nonlocal`; C# has no such statement. |
| `role/scope-declaration/bindings` | Python/PHP `global` and `nonlocal`; C# has no such statement. |
| `fact/yield/delegates` | C# has no `yield from`. |
| `kind/alias` | `using X = T;` is an import alias and is written as an `import-binding` with an `alias` (proven by `aliased-import`); C# has no other type-alias declaration. |
| `role/alias/typeParameters` | `using X = T;` is an import alias and is written as an `import-binding` with an `alias` (proven by `aliased-import`); C# has no other type-alias declaration. |
| `role/alias/underlyingType` | `using X = T;` is an import alias and is written as an `import-binding` with an `alias` (proven by `aliased-import`); C# has no other type-alias declaration. |
| `kind/defer` | Go. |
| `role/defer/call` | Go. |
| `kind/select` | Go. |
| `kind/select-arm` | Go. |
| `role/select/arms` | Go. |
| `role/select-arm/body` | Go. |
| `role/select-arm/communication` | Go. |
| `role/function/receiver` | Go. |
| `role/if/initializer` | Go. |
| `link/type/satisfies` | Go. |
| `role/break/label` | C# has no labeled `break` or `continue`; `goto label` is the jump. |
| `role/continue/label` | C# has no labeled `break` or `continue`; `goto label` is the jump. |
| `role/construction/callee` | A Python class called as a function. C# `new T()` always has `type` (or none, for target-typed `new()`). |
| `role/function/captures` | A lambda captures implicitly; there is no written capture list (PHP's `use`). |
| `role/import/alias` | The alias sits on the `import-binding`, proven by `aliased-import`. |
| `role/loop/normalCompletion` | Python's `for/else`, `try/else`. |
| `role/try/normalCompletion` | Python's `for/else`, `try/else`. |
| `role/throw/cause` | Python's `raise from`. |
| `role/type-expression/members` | C# has no written union type. |
| `role/type-parameter/default` | C# type parameters have no default. |
| `role/type/mixins` | PHP traits; C# has none (default interface methods are members of the interface). |
| `fact/field/optional` | TypeScript's `name?` on a property; a csharp field always exists, and a missing value is its nullable type |
