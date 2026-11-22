# Go: what the bridge does not translate yet

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
| a type or a function of a package outside both the module and the standard library | no `refers`, `target` or resolved type on it or on what it returns | the checker reads the module's own packages and the standard library of the Go installation; any other package is an empty stub |

## Not owed

| Item | Why |
|---|---|
| `fact/*/conversion` | Go has no interpolated strings |
| `kind/interpolation` | Go has no interpolated strings |
| `role/interpolation/parts` | Go has no interpolated strings |
| `kind/text` | Go has no interpolated strings |
| `fact/text/value` | Go has no interpolated strings |
| `fact/*/modifiers` | no modifier keywords; capitalisation lands in `effective` |
| `fact/access/nullConditional` | no `?.` |
| `fact/call/nullConditional` | no `?.` |
| `fact/index/nullConditional` | no `?.` |
| `fact/argument/label` | no named or by-reference arguments |
| `fact/argument/passing` | no named or by-reference arguments |
| `fact/assignment/prefix` | Go's `i++` is a statement; there is no `++i` |
| `fact/break/depth` | `break` takes a label, not a count |
| `fact/continue/depth` | `break` takes a label, not a count |
| `kind/comprehension` | no comprehensions |
| `kind/comprehension-clause` | no comprehensions |
| `fact/comprehension/form` | no comprehensions |
| `fact/comprehension-clause/form` | no comprehensions |
| `fact/comprehension-clause/async` | no comprehensions |
| `role/comprehension/clauses` | no comprehensions |
| `role/comprehension/element` | no comprehensions |
| `role/comprehension/key` | no comprehensions |
| `role/comprehension/value` | no comprehensions |
| `role/comprehension-clause/bindings` | no comprehensions |
| `role/comprehension-clause/condition` | no comprehensions |
| `role/comprehension-clause/iterable` | no comprehensions |
| `kind/directive` | Vue markup |
| `kind/element` | Vue markup |
| `kind/embedded-expression` | Vue markup |
| `kind/attribute` | Vue markup |
| `fact/directive/shorthand` | Vue markup |
| `fact/element/form` | Vue markup |
| `fact/element/resolves` | Vue markup |
| `fact/element/tag` | Vue markup |
| `role/attribute/value` | Vue markup |
| `role/directive/argument` | Vue markup |
| `role/directive/bindings` | Vue markup |
| `role/directive/iterable` | Vue markup |
| `role/directive/pattern` | Vue markup |
| `role/directive/value` | Vue markup |
| `role/element/attributes` | Vue markup |
| `role/element/children` | Vue markup |
| `role/embedded-expression/expression` | Vue markup |
| `link/directive/body` | Vue markup |
| `fact/dispatch/exhaustive` | Go's compiler never proves a `switch` exhaustive |
| `fact/entry/expansion` | no spread inside a composite literal |
| `kind/fallback` | no `??` |
| `fact/fallback/trigger` | no `??` |
| `role/fallback/replacement` | no `??` |
| `role/fallback/subject` | no `??` |
| `kind/conditional` | Go has no ternary |
| `role/conditional/condition` | Go has no ternary |
| `role/conditional/consequent` | Go has no ternary |
| `role/conditional/alternative` | Go has no ternary |
| `fact/function/async` | no `async`, `await` or generators |
| `fact/function/generator` | no `async`, `await` or generators |
| `fact/loop/async` | no `async`, `await` or generators |
| `kind/await` | no `async`, `await` or generators |
| `role/await/operand` | no `async`, `await` or generators |
| `kind/yield` | no `async`, `await` or generators |
| `fact/yield/delegates` | no `async`, `await` or generators |
| `role/yield/value` | no `async`, `await` or generators |
| `kind/resource-scope` | `defer` is its own kind; there is no `with` or `using` |
| `fact/resource-scope/async` | `defer` is its own kind; there is no `with` or `using` |
| `fact/resource-scope/form` | `defer` is its own kind; there is no `with` or `using` |
| `role/resource-scope/body` | `defer` is its own kind; there is no `with` or `using` |
| `role/resource-scope/resources` | `defer` is its own kind; there is no `with` or `using` |
| `kind/try` | no exceptions; `recover()` is a call and `panic(x)` has no cause |
| `kind/handler` | no exceptions; `recover()` is a call and `panic(x)` has no cause |
| `fact/handler/catchForm` | no exceptions; `recover()` is a call and `panic(x)` has no cause |
| `fact/handler/group` | no exceptions; `recover()` is a call and `panic(x)` has no cause |
| `role/try/body` | no exceptions; `recover()` is a call and `panic(x)` has no cause |
| `role/try/finally` | no exceptions; `recover()` is a call and `panic(x)` has no cause |
| `role/try/handlers` | no exceptions; `recover()` is a call and `panic(x)` has no cause |
| `role/try/normalCompletion` | no exceptions; `recover()` is a call and `panic(x)` has no cause |
| `role/handler/binding` | no exceptions; `recover()` is a call and `panic(x)` has no cause |
| `role/handler/body` | no exceptions; `recover()` is a call and `panic(x)` has no cause |
| `role/handler/caughtTypes` | no exceptions; `recover()` is a call and `panic(x)` has no cause |
| `role/handler/filter` | no exceptions; `recover()` is a call and `panic(x)` has no cause |
| `role/throw/cause` | no exceptions; `recover()` is a call and `panic(x)` has no cause |
| `fact/import/relativeLevel` | Python and TypeScript only |
| `fact/import/typeOnly` | Python and TypeScript only |
| `kind/import-binding` | a Go import binds a whole package (the alias is on the `import` itself), not a list of names |
| `role/import-binding/alias` | a Go import binds a whole package (the alias is on the `import` itself), not a list of names |
| `role/import/bindings` | a Go import binds a whole package (the alias is on the `import` itself), not a list of names |
| `fact/parameter/evaluated` | no defaults, optional marks, passing modes or keyword-only parameters |
| `fact/parameter/optional` | no defaults, optional marks, passing modes or keyword-only parameters |
| `fact/parameter/passing` | no defaults, optional marks, passing modes or keyword-only parameters |
| `fact/parameter/position` | no defaults, optional marks, passing modes or keyword-only parameters |
| `role/parameter/default` | no defaults, optional marks, passing modes or keyword-only parameters |
| `role/parameter/attributes` | no defaults, optional marks, passing modes or keyword-only parameters |
| `fact/pattern/operator` | no relational pattern |
| `fact/type-test/negated` | a type switch is never negated |
| `kind/scope-declaration` | no `global` or `nonlocal` |
| `fact/scope-declaration/form` | no `global` or `nonlocal` |
| `role/scope-declaration/bindings` | no `global` or `nonlocal` |
| `kind/annotation` | Go has no annotations; a struct tag is a bare string with no callee or arguments (the one borderline case: if a rule must read tags they need a new fact, not `annotation`) |
| `role/annotation/arguments` | Go has no annotations; a struct tag is a bare string with no callee or arguments (the one borderline case: if a rule must read tags they need a new fact, not `annotation`) |
| `role/annotation/callee` | Go has no annotations; a struct tag is a bare string with no callee or arguments (the one borderline case: if a rule must read tags they need a new fact, not `annotation`) |
| `role/constant/attributes` | Go has no annotations; a struct tag is a bare string with no callee or arguments (the one borderline case: if a rule must read tags they need a new fact, not `annotation`) |
| `role/enum-case/attributes` | Go has no annotations; a struct tag is a bare string with no callee or arguments (the one borderline case: if a rule must read tags they need a new fact, not `annotation`) |
| `role/field/attributes` | Go has no annotations; a struct tag is a bare string with no callee or arguments (the one borderline case: if a rule must read tags they need a new fact, not `annotation`) |
| `role/function/attributes` | Go has no annotations; a struct tag is a bare string with no callee or arguments (the one borderline case: if a rule must read tags they need a new fact, not `annotation`) |
| `role/type/attributes` | Go has no annotations; a struct tag is a bare string with no callee or arguments (the one borderline case: if a rule must read tags they need a new fact, not `annotation`) |
| `kind/enum-case` | no enum; `iota` constants are `constant` |
| `role/enum-case/value` | no enum; `iota` constants are `constant` |
| `kind/default-value` | C# |
| `role/default-value/writtenType` | C# |
| `link/field/hooks` | no hooks or promoted parameters (an embedded field's methods are not written nodes) |
| `link/type/implicitMembers` | no hooks or promoted parameters (an embedded field's methods are not written nodes) |
| `role/construction/arguments` | a Go composite literal has a `type` and an `initializer`, not arguments or a callee |
| `role/construction/callee` | a Go composite literal has a `type` and an `initializer`, not arguments or a callee |
| `role/function/captures` | closures capture without a list |
| `role/type/interfaces` | implicit satisfaction is the `satisfies` link; embedded interfaces are `bases` |
| `role/type/mixins` | implicit satisfaction is the `satisfies` link; embedded interfaces are `bases` |
| `role/arm/guard` | a Go `case` has no guard |
| `role/loop/normalCompletion` | Python's `for … else` |
| `role/module/statements` | Go has no top-level statements; everything is a declaration |
| `role/type-parameter/default` | Go type parameters have no defaults |
| `role/pattern/bindings` | a Go `case` pattern is one value or one type; the type switch's bound name is `type-test.binding` |
| `role/pattern/subpatterns` | a Go `case` pattern is one value or one type; the type switch's bound name is `type-test.binding` |
| `role/type-test/testedType` | Go tests a type only in a type switch, whose arms carry the types tested (`pattern.constrainedType`), so its type test has none of its own |
| `fact/field/optional` | TypeScript's `name?` on a property; a go field always exists, and a missing value is its nullable type |
