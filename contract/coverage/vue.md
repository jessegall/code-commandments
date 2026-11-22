# Vue: what the bridge does not translate yet

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

## Not owed

| Item | Why |
|---|---|
| `kind/comprehension` | neither a script nor a template expression has a comprehension |
| `kind/comprehension-clause` | neither a script nor a template expression has a comprehension |
| `role/comprehension/clauses` | neither a script nor a template expression has a comprehension |
| `role/comprehension/element` | neither a script nor a template expression has a comprehension |
| `role/comprehension/key` | neither a script nor a template expression has a comprehension |
| `role/comprehension/value` | neither a script nor a template expression has a comprehension |
| `role/comprehension-clause/bindings` | neither a script nor a template expression has a comprehension |
| `role/comprehension-clause/condition` | neither a script nor a template expression has a comprehension |
| `role/comprehension-clause/iterable` | neither a script nor a template expression has a comprehension |
| `fact/comprehension/form` | neither a script nor a template expression has a comprehension |
| `fact/comprehension-clause/async` | neither a script nor a template expression has a comprehension |
| `fact/comprehension-clause/form` | neither a script nor a template expression has a comprehension |
| `kind/constant` | a script `const` is a `variable` form `const`; class constants do not exist |
| `role/constant/attributes` | a script `const` is a `variable` form `const`; class constants do not exist |
| `role/constant/declarators` | a script `const` is a `variable` form `const`; class constants do not exist |
| `role/constant/declaredType` | a script `const` is a `variable` form `const`; class constants do not exist |
| `kind/default-value` | C# only |
| `role/default-value/writtenType` | C# only |
| `kind/defer` | Go only |
| `role/defer/call` | Go only |
| `kind/goto` | no `goto` in JavaScript |
| `role/goto/destination` | no `goto` in JavaScript |
| `link/goto/label` | no `goto` in JavaScript |
| `kind/scope-declaration` | Python and PHP only |
| `role/scope-declaration/bindings` | Python and PHP only |
| `fact/scope-declaration/form` | Python and PHP only |
| `kind/select` | Go only |
| `kind/select-arm` | Go only |
| `role/select/arms` | Go only |
| `role/select-arm/body` | Go only |
| `role/select-arm/communication` | Go only |
| `role/arm/guard` | a `case` clause has no guard |
| `role/construction/initializer` | C# only |
| `role/enum-case/attributes` | no decorator on an enum member |
| `role/function/captures` | PHP and Go only |
| `role/function/receiver` | PHP and Go only |
| `role/handler/caughtTypes` | a catch catches everything (coverage row); `when` is C# |
| `role/handler/filter` | a catch catches everything (coverage row); `when` is C# |
| `role/if/initializer` | Go's if initializer; Python's `else` of a loop and of a `try` |
| `role/loop/normalCompletion` | Go's if initializer; Python's `else` of a loop and of a `try` |
| `role/try/normalCompletion` | Go's if initializer; Python's `else` of a loop and of a `try` |
| `role/pattern/constrainedType` | C# patterns; `instanceof` binds nothing |
| `role/type-test/binding` | C# patterns; `instanceof` binds nothing |
| `fact/pattern/operator` | C# patterns; `instanceof` binds nothing |
| `fact/type-test/negated` | C# patterns; `instanceof` binds nothing |
| `role/throw/cause` | Python's `raise ... from` |
| `role/type/mixins` | PHP traits |
| `link/field/hooks` | PHP property hooks; Go's implicit interfaces (see the TypeScript table for the structural-typing caveat) |
| `link/type/satisfies` | PHP property hooks; Go's implicit interfaces (see the TypeScript table for the structural-typing caveat) |
| `fact/argument/label` | named arguments, `ref` and `out`, `break 2`, `except*` and relative-level imports are not JavaScript |
| `fact/argument/passing` | named arguments, `ref` and `out`, `break 2`, `except*` and relative-level imports are not JavaScript |
| `fact/break/depth` | named arguments, `ref` and `out`, `break 2`, `except*` and relative-level imports are not JavaScript |
| `fact/continue/depth` | named arguments, `ref` and `out`, `break 2`, `except*` and relative-level imports are not JavaScript |
| `fact/handler/group` | named arguments, `ref` and `out`, `break 2`, `except*` and relative-level imports are not JavaScript |
| `fact/import/relativeLevel` | named arguments, `ref` and `out`, `break 2`, `except*` and relative-level imports are not JavaScript |
| `fact/parameter/evaluated` | the only value is the documented default, which the comparator counts as absent |
| `fact/parameter/position` | the only value is the documented default, which the comparator counts as absent |
| `fact/parameter/passing` | the only value is the documented default, which the comparator counts as absent |
| `fact/return/namedResults` | Go only |
| `kind/resource-scope` | TypeScript has no statement that holds a body and releases its resources; a `using` declaration is a `variable` of form `using` |
| `fact/resource-scope/form` | TypeScript has no statement that holds a body and releases its resources; a `using` declaration is a `variable` of form `using` |
| `fact/resource-scope/async` | TypeScript has no statement that holds a body and releases its resources; a `using` declaration is a `variable` of form `using` |
| `role/resource-scope/resources` | TypeScript has no statement that holds a body and releases its resources; a `using` declaration is a `variable` of form `using` |
| `role/resource-scope/body` | TypeScript has no statement that holds a body and releases its resources; a `using` declaration is a `variable` of form `using` |
| `fact/*/conversion` | a Vue script is TypeScript, and a template interpolation `{{ }}` formats no value: neither has a conversion like Python's `!r` |
