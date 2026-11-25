# PHP: what the bridge does not translate yet

Each row is a construct the PHP bridge writes as `unsupported` (with its sub-expressions as `parts`, so the names
and calls inside are still read) or translates with a loss, and why.

| Construct | Written as | Why |
|---|---|---|
| `declare(strict_types=1)` | `unsupported`, construct `declare` | version 5 has no kind for a compiler directive |
| `__halt_compiler()`, inline HTML | `unsupported` | no kind; nothing after it is code |
| `list()` and `[...]` as an assignment target | `collection` form `array` | version 5 writes a destructuring target as a `pattern`; not translated yet |
| `echo`, `print`, `unset`, `isset`, `empty`, `exit`, `clone`, `eval`, `include` | `call` whose callee is a `reference` named for the keyword | the callee carries no `refers` to PHP's own symbol yet |
| a cast `(int) $x` | `cast` whose `targetType` is a keyword `type-expression` | the written type's name is not stated |
| a call's `target` | absent | php-parser resolves no callee; a compiler fact PHP has no tool for |
| a parse error's partial declaration | the statements php-parser recovered | an `incomplete` node for the part it dropped is not written yet |

## Not owed

| Item | Why |
|---|---|
| `fact/*/conversion` | PHP interpolation has no format conversion; `conversion` is Python's `!r`. |
| `fact/argument/passing` | PHP has no call-site `&` (removed in 5.4); by-reference is a parameter fact, which is proven (`flags`). |
| `fact/call/nullConditional` | PHP's `?->` is on the callee `access`, proven by `selectors`; the call itself has no `?()` form. |
| `fact/comprehension/form` | PHP has no comprehension; `array_map` is a call. |
| `fact/comprehension-clause/async` | PHP has no comprehension; `array_map` is a call. |
| `fact/comprehension-clause/form` | PHP has no comprehension; `array_map` is a call. |
| `kind/comprehension` | PHP has no comprehension; `array_map` is a call. |
| `kind/comprehension-clause` | PHP has no comprehension; `array_map` is a call. |
| `role/comprehension/clauses` | PHP has no comprehension; `array_map` is a call. |
| `role/comprehension/element` | PHP has no comprehension; `array_map` is a call. |
| `role/comprehension/key` | PHP has no comprehension; `array_map` is a call. |
| `role/comprehension/value` | PHP has no comprehension; `array_map` is a call. |
| `role/comprehension-clause/bindings` | PHP has no comprehension; `array_map` is a call. |
| `role/comprehension-clause/condition` | PHP has no comprehension; `array_map` is a call. |
| `role/comprehension-clause/iterable` | PHP has no comprehension; `array_map` is a call. |
| `fact/directive/shorthand` | Markup kinds: only a Vue template has them. A PHP `#[...]` is an `annotation`, not the markup `attribute`. |
| `fact/module/perInstance` | a Vue `<script setup>` only: a module of this language runs its top-level code once, not once per instance |
| `fact/element/form` | Markup kinds: only a Vue template has them. A PHP `#[...]` is an `annotation`, not the markup `attribute`. |
| `fact/element/resolves` | Markup kinds: only a Vue template has them. A PHP `#[...]` is an `annotation`, not the markup `attribute`. |
| `fact/element/tag` | Markup kinds: only a Vue template has them. A PHP `#[...]` is an `annotation`, not the markup `attribute`. |
| `kind/attribute` | Markup kinds: only a Vue template has them. A PHP `#[...]` is an `annotation`, not the markup `attribute`. |
| `kind/directive` | Markup kinds: only a Vue template has them. A PHP `#[...]` is an `annotation`, not the markup `attribute`. |
| `kind/element` | Markup kinds: only a Vue template has them. A PHP `#[...]` is an `annotation`, not the markup `attribute`. |
| `kind/embedded-expression` | Markup kinds: only a Vue template has them. A PHP `#[...]` is an `annotation`, not the markup `attribute`. |
| `link/directive/body` | Markup kinds: only a Vue template has them. A PHP `#[...]` is an `annotation`, not the markup `attribute`. |
| `role/attribute/value` | Markup kinds: only a Vue template has them. A PHP `#[...]` is an `annotation`, not the markup `attribute`. |
| `role/directive/argument` | Markup kinds: only a Vue template has them. A PHP `#[...]` is an `annotation`, not the markup `attribute`. |
| `role/directive/bindings` | Markup kinds: only a Vue template has them. A PHP `#[...]` is an `annotation`, not the markup `attribute`. |
| `role/directive/iterable` | Markup kinds: only a Vue template has them. A PHP `#[...]` is an `annotation`, not the markup `attribute`. |
| `role/directive/pattern` | Markup kinds: only a Vue template has them. A PHP `#[...]` is an `annotation`, not the markup `attribute`. |
| `role/directive/value` | Markup kinds: only a Vue template has them. A PHP `#[...]` is an `annotation`, not the markup `attribute`. |
| `role/element/attributes` | Markup kinds: only a Vue template has them. A PHP `#[...]` is an `annotation`, not the markup `attribute`. |
| `role/element/children` | Markup kinds: only a Vue template has them. A PHP `#[...]` is an `annotation`, not the markup `attribute`. |
| `role/embedded-expression/expression` | Markup kinds: only a Vue template has them. A PHP `#[...]` is an `annotation`, not the markup `attribute`. |
| `fact/function/async` | PHP has no `async`/`await` syntax (Fibers and promises are library calls). |
| `fact/loop/async` | PHP has no `async`/`await` syntax (Fibers and promises are library calls). |
| `kind/await` | PHP has no `async`/`await` syntax (Fibers and promises are library calls). |
| `role/await/operand` | PHP has no `async`/`await` syntax (Fibers and promises are library calls). |
| `fact/handler/group` | `except*` is Python's. |
| `fact/import/relativeLevel` | A PHP `use` has no leading dots and no type-only form (those are Python and TypeScript). |
| `fact/import/typeOnly` | A PHP `use` has no leading dots and no type-only form (those are Python and TypeScript). |
| `fact/index/nullConditional` | PHP has `?->` but no null-safe `?[ ]`. |
| `fact/parameter/evaluated` | A PHP default is evaluated at the call (the documented default `evaluated: call`) and any parameter can be passed by position or name (`position: either`): both are documented defaults and count as absent, so no PHP source can write them. `optional` is TypeScript's `x?`; a PHP default value is the `default` role. |
| `fact/parameter/position` | A PHP default is evaluated at the call (the documented default `evaluated: call`) and any parameter can be passed by position or name (`position: either`): both are documented defaults and count as absent, so no PHP source can write them. `optional` is TypeScript's `x?`; a PHP default value is the `default` role. |
| `fact/parameter/optional` | A PHP default is evaluated at the call (the documented default `evaluated: call`) and any parameter can be passed by position or name (`position: either`): both are documented defaults and count as absent, so no PHP source can write them. `optional` is TypeScript's `x?`; a PHP default value is the `default` role. |
| `fact/pattern/operator` | PHP patterns are only value patterns (`case 1:`, `1, 2 =>`); relational, type, binding and nested patterns do not exist. |
| `role/pattern/bindings` | PHP patterns are only value patterns (`case 1:`, `1, 2 =>`); relational, type, binding and nested patterns do not exist. |
| `role/pattern/constrainedType` | PHP patterns are only value patterns (`case 1:`, `1, 2 =>`); relational, type, binding and nested patterns do not exist. |
| `role/pattern/subpatterns` | PHP patterns are only value patterns (`case 1:`, `1, 2 =>`); relational, type, binding and nested patterns do not exist. |
| `fact/resource-scope/async` | PHP has no `with`/`using`/`lock` statement. |
| `fact/resource-scope/form` | PHP has no `with`/`using`/`lock` statement. |
| `kind/resource-scope` | PHP has no `with`/`using`/`lock` statement. |
| `role/resource-scope/body` | PHP has no `with`/`using`/`lock` statement. |
| `role/resource-scope/resources` | PHP has no `with`/`using`/`lock` statement. |
| `fact/return/namedResults` | Go's bare return with named results. |
| `fact/tuple-element/label` | PHP has no tuple type; an array shape is a docblock. |
| `kind/tuple-element` | PHP has no tuple type; an array shape is a docblock. |
| `kind/tuple-type` | PHP has no tuple type; an array shape is a docblock. |
| `role/tuple-element/type` | PHP has no tuple type; an array shape is a docblock. |
| `role/tuple-type/elements` | PHP has no tuple type; an array shape is a docblock. |
| `fact/type-test/negated` | PHP `instanceof` has no negated form and binds no name; `!($x instanceof T)` is a `unary` around the test. |
| `role/type-test/binding` | PHP `instanceof` has no negated form and binds no name; `!($x instanceof T)` is a `unary` around the test. |
| `kind/alias` | PHP declares no type alias; `use X as Y` is an import alias (proven by `aliased-import`). `class_alias()` is a call. |
| `role/alias/typeParameters` | PHP declares no type alias; `use X as Y` is an import alias (proven by `aliased-import`). `class_alias()` is a call. |
| `role/alias/underlyingType` | PHP declares no type alias; `use X as Y` is an import alias (proven by `aliased-import`). `class_alias()` is a call. |
| `kind/default-value` | C#'s `default`. |
| `role/default-value/writtenType` | C#'s `default`. |
| `kind/defer` | Go's `defer`. |
| `role/defer/call` | Go's `defer`. |
| `kind/select` | Go only (`select`, a method receiver, `if x := f();`, implicit interface satisfaction). |
| `kind/select-arm` | Go only (`select`, a method receiver, `if x := f();`, implicit interface satisfaction). |
| `role/select/arms` | Go only (`select`, a method receiver, `if x := f();`, implicit interface satisfaction). |
| `role/select-arm/body` | Go only (`select`, a method receiver, `if x := f();`, implicit interface satisfaction). |
| `role/select-arm/communication` | Go only (`select`, a method receiver, `if x := f();`, implicit interface satisfaction). |
| `role/function/receiver` | Go only (`select`, a method receiver, `if x := f();`, implicit interface satisfaction). |
| `role/if/initializer` | Go only (`select`, a method receiver, `if x := f();`, implicit interface satisfaction). |
| `link/type/satisfies` | Go only (`select`, a method receiver, `if x := f();`, implicit interface satisfaction). |
| `kind/type-parameter` | PHP has no generics and no function-type syntax (`@template` is a docblock; `callable` and `Closure` are named types). |
| `role/type-parameter/constraint` | PHP has no generics and no function-type syntax (`@template` is a docblock; `callable` and `Closure` are named types). |
| `role/type-parameter/default` | PHP has no generics and no function-type syntax (`@template` is a docblock; `callable` and `Closure` are named types). |
| `role/type/typeParameters` | PHP has no generics and no function-type syntax (`@template` is a docblock; `callable` and `Closure` are named types). |
| `role/function/typeParameters` | PHP has no generics and no function-type syntax (`@template` is a docblock; `callable` and `Closure` are named types). |
| `role/call/typeArguments` | PHP has no generics and no function-type syntax (`@template` is a docblock; `callable` and `Closure` are named types). |
| `role/type-expression/typeArguments` | PHP has no generics and no function-type syntax (`@template` is a docblock; `callable` and `Closure` are named types). |
| `role/type-expression/parameters` | PHP has no generics and no function-type syntax (`@template` is a docblock; `callable` and `Closure` are named types). |
| `role/type-expression/returnType` | PHP has no generics and no function-type syntax (`@template` is a docblock; `callable` and `Closure` are named types). |
| `role/arm/guard` | A PHP `match` or `switch` arm has no `when`. |
| `role/break/label` | PHP jumps out of loops by depth (`break 2`, the `depth` fact), not by label. |
| `role/continue/label` | PHP jumps out of loops by depth (`break 2`, the `depth` fact), not by label. |
| `role/handler/filter` | `catch ... when (...)` is C#'s. |
| `role/import/alias` | PHP puts the alias on the `import-binding` (`use A\B as C`), proven by `aliased-import`; an alias of a whole import is Python's and Go's. |
| `role/label/statement` | A PHP label stands alone (`done:`); it does not wrap the statement after it. |
| `role/loop/normalCompletion` | Python's `for/else` and `try/else`. |
| `role/try/normalCompletion` | Python's `for/else` and `try/else`. |
| `role/throw/cause` | Python's `raise from`; a PHP previous failure is a constructor argument. |
| `role/variable/declaredType` | PHP's `static $n` is untyped; a typed local does not exist. |
| `role/construction/initializer` | PHP has no object or collection initializer after `new`; properties are set by the constructor or by assignments |
| `kind/no-op` | php-parser drops an empty statement: a lone `;` leaves no node, and `while ($x);` an empty body, so nothing stands to be written; a rule counting statements sees the same count either way |
| `fact/*/incomplete` | php-parser recovers the statements around an error but writes no partial declaration for what it dropped |
| `fact/field/optional` | TypeScript's `name?` on a property; a php field always exists, and a missing value is its nullable type |
