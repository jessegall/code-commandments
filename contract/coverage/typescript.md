# TypeScript: what the bridge does not translate yet

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

## Not owed

| Item | Why |
|---|---|
| `kind/attribute` | a markup attribute; only a Vue template has one |
| `role/attribute/value` | a markup attribute; only a Vue template has one |
| `kind/directive` | a Vue directive |
| `role/directive/argument` | a Vue directive |
| `role/directive/bindings` | a Vue directive |
| `role/directive/iterable` | a Vue directive |
| `role/directive/pattern` | a Vue directive |
| `role/directive/value` | a Vue directive |
| `link/directive/body` | a Vue directive |
| `fact/directive/shorthand` | a Vue directive |
| `kind/element` | Vue markup; TypeScript has no template (JSX is written as `unsupported`, coverage row) |
| `role/element/attributes` | Vue markup; TypeScript has no template (JSX is written as `unsupported`, coverage row) |
| `role/element/children` | Vue markup; TypeScript has no template (JSX is written as `unsupported`, coverage row) |
| `fact/element/form` | Vue markup; TypeScript has no template (JSX is written as `unsupported`, coverage row) |
| `fact/element/resolves` | Vue markup; TypeScript has no template (JSX is written as `unsupported`, coverage row) |
| `fact/element/tag` | Vue markup; TypeScript has no template (JSX is written as `unsupported`, coverage row) |
| `kind/embedded-expression` | Vue markup; TypeScript has no template (JSX is written as `unsupported`, coverage row) |
| `role/embedded-expression/expression` | Vue markup; TypeScript has no template (JSX is written as `unsupported`, coverage row) |
| `kind/comprehension` | TypeScript has no comprehension; a chain of `map` and `filter` calls is calls |
| `kind/comprehension-clause` | TypeScript has no comprehension; a chain of `map` and `filter` calls is calls |
| `role/comprehension/clauses` | TypeScript has no comprehension; a chain of `map` and `filter` calls is calls |
| `role/comprehension/element` | TypeScript has no comprehension; a chain of `map` and `filter` calls is calls |
| `role/comprehension/key` | TypeScript has no comprehension; a chain of `map` and `filter` calls is calls |
| `role/comprehension/value` | TypeScript has no comprehension; a chain of `map` and `filter` calls is calls |
| `role/comprehension-clause/bindings` | TypeScript has no comprehension; a chain of `map` and `filter` calls is calls |
| `role/comprehension-clause/condition` | TypeScript has no comprehension; a chain of `map` and `filter` calls is calls |
| `role/comprehension-clause/iterable` | TypeScript has no comprehension; a chain of `map` and `filter` calls is calls |
| `fact/comprehension/form` | TypeScript has no comprehension; a chain of `map` and `filter` calls is calls |
| `fact/comprehension-clause/async` | TypeScript has no comprehension; a chain of `map` and `filter` calls is calls |
| `fact/comprehension-clause/form` | TypeScript has no comprehension; a chain of `map` and `filter` calls is calls |
| `kind/constant` | the kind is a class or module constant (PHP `const`, C# `const`). TypeScript's `const` is a `variable` with form `const` (sample `flags`), and a class constant is a `static readonly` field |
| `role/constant/attributes` | the kind is a class or module constant (PHP `const`, C# `const`). TypeScript's `const` is a `variable` with form `const` (sample `flags`), and a class constant is a `static readonly` field |
| `role/constant/declarators` | the kind is a class or module constant (PHP `const`, C# `const`). TypeScript's `const` is a `variable` with form `const` (sample `flags`), and a class constant is a `static readonly` field |
| `role/constant/declaredType` | the kind is a class or module constant (PHP `const`, C# `const`). TypeScript's `const` is a `variable` with form `const` (sample `flags`), and a class constant is a `static readonly` field |
| `kind/default-value` | C# `default` and `default(T)`; TypeScript has no such expression |
| `role/default-value/writtenType` | C# `default` and `default(T)`; TypeScript has no such expression |
| `kind/defer` | Go's `defer`; the TypeScript form is `finally`, which is a `try` role |
| `role/defer/call` | Go's `defer`; the TypeScript form is `finally`, which is a `try` role |
| `kind/goto` | JavaScript has no `goto` |
| `role/goto/destination` | JavaScript has no `goto` |
| `link/goto/label` | JavaScript has no `goto` |
| `kind/scope-declaration` | Python `global` and `nonlocal`, PHP `global`; a closure captures by name with no declaration |
| `role/scope-declaration/bindings` | Python `global` and `nonlocal`, PHP `global`; a closure captures by name with no declaration |
| `fact/scope-declaration/form` | Python `global` and `nonlocal`, PHP `global`; a closure captures by name with no declaration |
| `kind/select` | Go's `select` over channels |
| `kind/select-arm` | Go's `select` over channels |
| `role/select/arms` | Go's `select` over channels |
| `role/select-arm/body` | Go's `select` over channels |
| `role/select-arm/communication` | Go's `select` over channels |
| `role/arm/guard` | a `case` clause has no guard in JavaScript |
| `role/construction/initializer` | C# object and collection initializers; `new X({...})` passes an ordinary argument |
| `role/enum-case/attributes` | TypeScript does not allow a decorator on an enum member |
| `role/function/captures` | PHP's `use (...)`; a TypeScript closure captures without listing |
| `role/function/receiver` | Go's receiver; a method's `this` is not a parameter node |
| `role/handler/caughtTypes` | a TypeScript `catch` catches everything and filters nothing (coverage row: the handler is `catch-all`, the annotation of `catch (e: unknown)` is left out); `when` is C# |
| `role/handler/filter` | a TypeScript `catch` catches everything and filters nothing (coverage row: the handler is `catch-all`, the annotation of `catch (e: unknown)` is left out); `when` is C# |
| `role/if/initializer` | Go's `if x := f(); ...` |
| `role/loop/normalCompletion` | Python's `for ... else` and `try ... else`; JavaScript has neither |
| `role/try/normalCompletion` | Python's `for ... else` and `try ... else`; JavaScript has neither |
| `role/pattern/constrainedType` | C# type, relational and `is not` patterns; TypeScript's `instanceof` binds nothing and a negation is a `unary` around the `type-test` |
| `role/type-test/binding` | C# type, relational and `is not` patterns; TypeScript's `instanceof` binds nothing and a negation is a `unary` around the `type-test` |
| `fact/pattern/operator` | C# type, relational and `is not` patterns; TypeScript's `instanceof` binds nothing and a negation is a `unary` around the `type-test` |
| `fact/type-test/negated` | C# type, relational and `is not` patterns; TypeScript's `instanceof` binds nothing and a negation is a `unary` around the `type-test` |
| `role/throw/cause` | Python's `raise ... from`; a TypeScript `{ cause }` is an argument of the `construction` (the contract keeps a wrapped exception an argument) |
| `role/type/mixins` | PHP traits |
| `link/field/hooks` | PHP property hooks; a TypeScript accessor is a standalone member of form `get` or `set`, not hooked to a field |
| `link/type/satisfies` | the contract gives `satisfies` to Go's implicit interface satisfaction; TypeScript names its interfaces with `implements` (role `interfaces`). Structural typing is not modelled; if the main agent wants it, this moves to the gaps |
| `fact/*/conversion` | Python's `!r` of an f-string expression |
| `fact/argument/label` | no named arguments and no `ref` or `out` in TypeScript |
| `fact/argument/passing` | no named arguments and no `ref` or `out` in TypeScript |
| `fact/break/depth` | PHP's `break 2`; TypeScript jumps by label |
| `fact/continue/depth` | PHP's `break 2`; TypeScript jumps by label |
| `fact/handler/group` | Python's `except*` |
| `fact/import/relativeLevel` | Python's leading dots |
| `fact/parameter/evaluated` | each has one TypeScript value and it is the documented default (`call`, `either`, `value`), which a bridge leaves absent; the comparator counts a default as absent (`expected/README.md`), so no sample can state it |
| `fact/parameter/position` | each has one TypeScript value and it is the documented default (`call`, `either`, `value`), which a bridge leaves absent; the comparator counts a default as absent (`expected/README.md`), so no sample can state it |
| `fact/parameter/passing` | each has one TypeScript value and it is the documented default (`call`, `either`, `value`), which a bridge leaves absent; the comparator counts a default as absent (`expected/README.md`), so no sample can state it |
| `fact/return/namedResults` | Go's bare return with named results |
| `kind/resource-scope` | TypeScript has no statement that holds a body and releases its resources; a `using` declaration is a `variable` of form `using` |
| `fact/resource-scope/form` | TypeScript has no statement that holds a body and releases its resources; a `using` declaration is a `variable` of form `using` |
| `fact/resource-scope/async` | TypeScript has no statement that holds a body and releases its resources; a `using` declaration is a `variable` of form `using` |
| `role/resource-scope/resources` | TypeScript has no statement that holds a body and releases its resources; a `using` declaration is a `variable` of form `using` |
| `role/resource-scope/body` | TypeScript has no statement that holds a body and releases its resources; a `using` declaration is a `variable` of form `using` |
