# Coverage

Every fact the four inventories list as must-carry, the field of [`CONTRACT.md`](CONTRACT.md) that
carries it, who fills it, and the sample that shows it. The sources are the must-carry lists in
[`inventory/`](inventory/).

- **Who** follows the contract's *Who fills it*. `bridge` means the fact is in the stream. `engine`
  means the engine derives it from the fields named, after reading the stream.
- **Shown in** names the sample under [`samples/`](samples/) that carries the fact. `—` means the
  fixture a sample was drawn from has no such construct; the schema and `reader_test.go` still cover
  the field.

`go test ./contract/...` from the repository root reads every sample through the strict reader, validates
each line against the schema, and checks every span, line and comment against the fixture's own bytes.
`TestEverySampleShowsWhatCoverageSaysItShows` holds the samples to the facts this table says they show.

## Shared by every language

| fact | field | who | shown in |
|---|---|---|---|
| node kind, as the parser names it | `kind` | bridge | all five |
| statement / expression / member / type / pattern / markup | `role` | bridge | all five |
| language-neutral questions (function, loop, bail-out, call, …) | `is` | bridge | all five |
| byte span `[start, end)`, UTF-8, trivia excluded, and start line | `span` | bridge | all five, checked against the fixtures |
| end line, line count | derived from `span` and the file's bytes | engine | — |
| file path | `file.path` | bridge | all five |
| ordered children, and the slot each fills (structural hash, sub-node names) | `children`, `field` | bridge | all five |
| parent and ancestors | rebuilt from `children` (`Node.Parent()`) | engine | all five (`TestLinksParents`) |
| source text of a node, re-parseable comment text | the file's bytes at `span`, `comment.text` | engine | all five |
| every comment with its kind, text and span | `file.comments[]` | bridge | all five |
| every comment attached to its node, not only the last docblock | `comment.attached`, `comment.trailing` | bridge | php, python, csharp, typescript, vue |
| syntax errors per file | `file.errors` | bridge | all five |
| resolver ran or not, so absence means "could not" | `file.resolver` | bridge | python, csharp, typescript, vue |
| resolution counts, span drift | `trailer.resolution` (`typed`, `resolved`, `unjoined`) | bridge | python, csharp, typescript |
| structural and shape hashes, duplicates, near-duplicates | `kind`, `field`, `name`, `literal`, `value`, `operator` | engine | — |
| guard, branch-condition, bail-out, nesting-depth predicates | `is`, `children`, parents | engine | — |

## PHP

| fact | field | who | shown in |
|---|---|---|---|
| class / interface / trait / enum, enum case and its backing literal | `kind` `Stmt_Class` …, `Stmt_EnumCase` + `expr` child `literal`/`value` | bridge | php (`Stmt_Class`) |
| function / method / closure / arrow function, with params | `kind`, `is: function`, `Param` children | bridge | php (`Stmt_ClassMethod`, `Param`) |
| param name, type, default, promoted, variadic, by-ref, visibility | `name`, `declared`, `default` child, `flags` (`promoted`, `variadic`, `by-ref`), `modifiers` | bridge | php (`promoted`, `public readonly`) |
| properties and hooks, constants, trait use | `Stmt_Property`/`PropertyItem`/`PropertyHook`, `Stmt_ClassConst`, `Stmt_TraitUse` | bridge | php (the `get =>` hook in `LabelPrintDefaults`, `SEPARATOR` in `AccessAuditor`, `use OptionalOrMissing` in `OptCoords`) |
| assignments, compound assignments, inc/dec | `kind`, `operator`, `is: assignment` | bridge | php (`$sum = 0`, `$sum += $amount` in `BasketTotaller`) |
| comparison / logical / coalesce operators | `kind` `Expr_BinaryOp_*`, `operator`, `is: comparison` | bridge | php (`!==`, `&&` in `SlackNotifier`) |
| ternary (full and short), match + arms, switch + case, if/elseif/else | `kind`, `is: branch`, `flags: short-ternary` | bridge | php (ternaries and `match (true)` in `GradeCalculator`, `if`/`elseif` in `DiscountTier`, `switch` in `CarrierPicker`) |
| loops with step, value and key vars | `kind`, `field` (`loop`, `valueVar`, `keyVar`), `flags: step` | bridge | php (`foreach` in `SlackNotifier` and `BasketTotaller`) |
| try / catch with caught types, throw | `Stmt_Catch` `declared` (a union for several), `is: catch`, `is: throw` | bridge | php (`catch (\Throwable $e)` in `SlackNotifier`) |
| calls (method, static, function, nullsafe) with positional / named / unpacked args | `kind`, `is: call`/`null-safe`, `Arg` `flags` (`named`, `spread`) | bridge | php (`Expr_MethodCall`) |
| `new`, property / array-dim / class-const / const fetch, variable | `kind`, `is: construction`/`member-access`/`identifier`/`self-reference` | bridge | php (`Expr_PropertyFetch`, `$this`) |
| literals verbatim | `literal`, `value` (numbers as decimal strings) | bridge | php (`'#ops'`, `0`) |
| attributes and their args | `AttributeGroup`/`Attribute`/`Arg` children, `refers` on the name | bridge | php (`#[Sinful(...)]`) |
| modifiers final, abstract, readonly, static, visibility | `modifiers` | bridge | php (`final`, `public readonly`) |
| `?T` vs `T\|null` | `declared.nullable` + node `flags: nullable-sugar` | bridge | php (`?GiftCard`) |
| type as written: nullable, union, intersection, name | `role: type` children, `declared`/`returns` (`kind`, `members`, `nullable`) | bridge | php |
| names resolved at parse time (FQCN) | `refers` on `Name_FullyQualified`, `declared.name` | bridge | php |
| `self` / `static` / `parent` kept apart from a class name | `Name` `name` (`self`), no `refers` | bridge | php (`self::SEPARATOR` in `AccessAuditor`) |
| declared type rendered, union members, single class of a union | `declared`/`returns` `text`, `members`, `name` | bridge | php |
| docblock `@var`/`@param`/`@return`/`@see` types and refs, resolved through the file's `use` nodes | `comment.text` + `Stmt_Use` `refers`; `refs` | engine | php (docblock on `Checkout`) |
| local variable flow, closure capture, typed foreach, receiver type | `children`, `declared`, `refers`, parents | engine | — |
| traced-variable interaction kinds | `kind` + parent `field` | engine | — |
| class / interface / trait hierarchy in the scan | `extends`/`implements`/`traits` children with `refers` | engine | php (`Stmt_Class`) |
| hierarchy and members of vendor classes | `program.symbols` (`extends`, `implements`, `members` with `returns`, `parameters`, `documented`), closed over ancestors and member types | bridge | php (`Sinful`, `PhantomNullable` and their ancestors) |
| method-declaration lookup across inheritance, overrides | `symbol` + hierarchy + `program.symbols` | engine | php |
| call graph (callers of a declaration) | `target` | engine | — |
| per-class field / return / param type index, declaring class | `declared`, `returns`, `symbol` | engine | php |
| collection element type (docblock generic, `#[DataCollectionOf]`) | `Type.element`, from the comment text and the attribute's args | engine | — |
| value vs service classification | `declared` of each field, recursively, with `program.symbols` | engine | — |
| value flow over class fields | `children`, `declared`, `target`, `flags` | engine | — |
| namespace dependency graph | `refers`, `symbol` | engine | php |
| enum case-value index, constant `__toString` values | `Stmt_EnumCase` + `literal`/`value`, `Stmt_Return` children | engine | — |
| Laravel / Spatie / Concurrent / PhpTypes facts (facades, routes, bindings, Data attributes, `Option`) | `refers` on names and attributes, `target`, `value` of string args, `program.symbols` | engine | php (`#[Sinful]` attribute `refers`) |

## Vue

| fact | field | who | shown in |
|---|---|---|---|
| element vs text vs comment vs root | `kind` `Element`/`Text`/`Component`; comments in `file.comments` with `kind: markup` | bridge | vue |
| tag name, case kept | `Element.name` | bridge | vue (`address`, `a`) |
| attributes in order with their own spans | `Attribute` children in field `attributes`, `name`, `value` | bridge | vue (`class="contact-card"`) |
| directive name, argument and modifiers, separately | `Directive` + `extras.vue.directive` (`name`, `modifiers`) + child in field `arg` | bridge | vue (`:href`) |
| a dynamic argument as an expression | the `arg` child is a TypeScript expression | bridge | — |
| bound vs static attribute | `kind` `Directive` vs `Attribute` | bridge | vue |
| shorthand `:` `@` `#` | `flags: shorthand` | bridge | vue (`:href`) |
| file-absolute spans for every node, template expressions included | `span` | bridge | vue (the `:href` template literal, the interpolation) |
| raw text, interpolation bodies | `Text` span; `Interpolation` with its expression in field `value` | bridge | vue (`{{ customer.emailAddress }}`) |
| comment text and span (`<!-- @sin -->` markers) | `file.comments[]` `kind: markup`, `attached` | bridge | vue (`@example` marker) |
| SFC blocks in order, with their attributes (`setup`, `lang`, `scoped`, `generic`, `src`) | `Block` children in field `blocks`, `Attribute` children | bridge | vue (`<script setup lang="ts">`, `<template>`) |
| `v-for` aliases and iterable, destructuring kept | `Directive` children in fields `alias` and `iterable` | bridge | vue (`perk in option.perks` in `DeliveryOptionCard`) |
| parsed binding and interpolation expressions | TypeScript nodes under `value` | bridge | vue |
| `v-if`/`v-else-if`/`v-else` chains | sibling order in `children` | engine | vue (the three `<span>`s in `StockIndicator`) |
| component-ness of a tag | `Element.name` | engine | vue |
| which file a component tag resolves to | the script's import `resolves` + `program.aliases` | bridge | — |
| prop types down and up the render tree, cross-file types | `defineProps` type argument, imports' `resolves`, `resolved` | engine | vue (`defineProps<{ customer: CustomerData }>()`) |
| checker-resolved types of script locals | `resolved` with `origin: compiler` | bridge | typescript (the same walker types a script block; this Vue fixture's only expressions go through `defineProps`, which plain `tsc` cannot type, so they stay absent) |
| a hand-written type mirroring a PHP Data class | TS `InterfaceDeclaration`/`TypeAliasDeclaration` fields, beside the PHP stream's class and its `#[TypeScript]` attribute | engine | — |
| structural hashes across components | `kind`, `field`, `name`, `value` | engine | — |

## TypeScript

| fact | field | who | shown in |
|---|---|---|---|
| every statement and declaration (imports, classes, interfaces, aliases, functions, variables) | `kind` (TypeScript `SyntaxKind`), `role`, `field` | bridge | typescript |
| spans absolute to the file a script sits in | `span` | bridge | typescript, vue |
| type nodes, and an opaque leaf for anything unmodelled | `role: type` children; `Type.kind` incl. `opaque`, `parameter`, `literal`, `function` | bridge | typescript (`Promise<number[]>`) |
| modifiers, TS's implicit `public` | `modifiers` (implicit ones not written) | bridge | typescript (`export`, `async`) |
| operators | `operator` | bridge | typescript (`===`) |
| comments and JSDoc | `file.comments[]` `kind` `line`/`block`/`doc`, `attached` | bridge | typescript |
| field and parameter optionality (`?` and a type admitting `undefined`) | `flags: optional`, `declared.nullable` | bridge | typescript (`queue?`, `lastError?` in `label-printer`) |
| own field of the enclosing class for a `this.x?.y` chain | `self-reference` + `member-access` + parents | engine | — |
| sound literal and expression types | `resolved` (`origin: compiler`) | bridge | typescript |
| per-function structural and shape hashes | `kind`, `field`, `name`, `value` | engine | typescript |
| imports: bindings, source, type-only, resolved file | `ImportDeclaration` children, `extras.typescript.typeOnly`, `resolves` | bridge | vue (`import type`) |
| calls resolving to their declaration | `target` (`symbol`, `type`, `name`) | bridge | typescript (`fetch`, `Array.map`, `Body.json`, `ErrorConstructor.new`) |
| `defineProps` with a named type from elsewhere | the type argument's `refers`, the import's `resolves` | bridge | vue |
| module path aliases | `program.aliases` | bridge | — |

## Python

| fact | field | who | shown in |
|---|---|---|---|
| byte spans on every node, and on comments | `span` | bridge | python |
| mypy's type per expression, joined by exact span | `resolved` (`text`, `name`, `nullable`, `constructs`), `trailer.resolution.unjoined` | bridge | python (68 of 113 expressions typed; the 1 unjoined is mypy's own f-string piece `{code` in `voucher_guards`, a span `ast` has no node for) |
| `def`: name, params, body, return annotation, decorators, async | `FunctionDef`, `name`, `arguments` child, `returns`, `decorator_list` children, `flags: async` | bridge | python |
| class: name, bases (incl. keyword bases), body, decorators | `ClassDef`, `bases`/`keywords`/`decorator_list` children | bridge | python |
| param: name, `*`/`**`, annotation, default, keyword-only | `arg` `name`, `declared`, the `arguments` fields `vararg`/`kwarg`/`kwonlyargs`/`defaults` | bridge | python (`discount: int`) |
| assign / annotated assign / augmented assign | `Assign`/`AnnAssign`/`AugAssign`, `operator`, `declared` | bridge | python (`self.lines = lines`) |
| imports: names, aliases, module, relative level | `Import`/`ImportFrom`, `alias` children, `extras.python.level` | bridge | python (`import json`, `from pathlib import Path`, a level-2 relative import in `billing/invoice`) |
| if / for / while / try / except (+ `except*`) / with / match / case | `kind`, `field`, `flags: group` | bridge | python (`if` in `cli`; `while`, `for`, `match`/`case` in `packing`) |
| return / raise with cause / break / continue / pass / assert / del / global / type alias | `kind`, `is: bail-out` | bridge | python (`Return`, `raise SettingsMissing(...)`) |
| every expression kind and its props | `kind` (ast class), `field` (ast field) | bridge | python (`BinOp`, `Call`, `Attribute`) |
| literal type and value | `literal` (`string`, `bytes`, `int`, `float`, `bool`, `null`, `ellipsis`, `interpolated`), `value` | bridge | python (the docstring) |
| operators, and a chained comparison's operator list | `operator`, `extras.python.operators` | bridge | python (`-`) |
| f-string parts (text, format spec, fields) | `JoinedStr` / `FormattedValue` children | bridge | python (`f"voucher {code} is refused"` in `voucher_guards`) |
| decorators as expressions | `decorator_list` children | bridge | python (`@classmethod` in `voucher_guards`) |
| docstrings | a `doc` comment `attached` to its def or class; the string statement stays in the tree | bridge | python |
| `#` comments, with Sphinx `#:` bodies | `file.comments[]` `kind: line`, `text` | bridge | python (`# @sin CeremonyDocblock`) |
| module name, package layout | `file.module`, `program.packages` | bridge | python |
| call targets (nested defs, imports, `self`/`cls`, typed receivers, first base) | `target` | engine | — |
| import targets | `resolves` | engine | — |
| class ancestry, enums, dataclasses, TypedDicts | `bases` children + `decorator_list`, resolved names | engine | python |
| attribute-flow tallies, resource reach, constant vocabulary | `children`, `resolved.name`, `resolved.constructs`, `target` | engine | python |
| which files are judged vs only inform | `file.context` | bridge | python (`shop/settings.py`, which `cli` imports from) |

## C#

| fact | field | who | shown in |
|---|---|---|---|
| `SyntaxKind` name, role, span, ordered children | `kind`, `role`, `span`, `children` | bridge | csharp |
| the slot a child fills | `field` (Roslyn's property name) | bridge | csharp |
| name, text of literals, operator, modifiers | `name`, `literal`/`value`, `operator`, `modifiers` | bridge | csharp (`public sealed`) |
| a `for` loop's step expressions | `flags: step` | bridge | csharp (`page = source.After(page)` in `PageReader`) |
| comments `line`/`block`/`doc` with text and span | `file.comments[]` | bridge | csharp |
| a comment that reads as code | `comment.extras.csharp.code` | bridge | — |
| doc `cref`s: as written, resolved symbol, longest resolving owner, owned here, blind | `comment.refs[]` (`text`, `symbol`, `owner`, `ownedHere`, `blind`) | bridge | csharp (`Basket` resolved, `ShoppingCart` dangling) |
| a base list, written | `BaseList` child | bridge | csharp (`: IAuditFailure` in `Audits`) |
| resolved type of expressions, parameters, catch declarations | `resolved`, `declared` (`text` `global::…`, `nullable`, `valueType`, `args`) | bridge | csharp |
| types nested in a generic or array | `Type.args`, recursively | bridge | csharp (`IEnumerable<string>`) |
| compile-time constants | `constant` | bridge | csharp (`"DIG-"`) |
| `!` over a declared-nullable operand | `extras.csharp.forgivesNull` | bridge | csharp (`lastScan!` in `Tracking`; the `null!` beside it carries none) |
| call and creation targets, joinable to declarations | `target` (original definition) and `symbol` | bridge | csharp (5 of 5 resolved) |
| members that override or implement | `inherited` | bridge | csharp (`CountMismatch.Details`, implementing `IAuditFailure`) |
| test project files | `file.test` | bridge | — |
| referenced-assembly types and their hierarchy | `program.symbols` | bridge | csharp |
| namespace graph, state flow | `resolved`, `target`, `symbol`, `children` | engine | csharp |
