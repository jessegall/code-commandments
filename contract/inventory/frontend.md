# Frontend Engine Inventory — Vue SFC + TypeScript

Scope covered: `src/Vue/**` (Codebase, Query, Element, ElementMatch, Attribute, Directive, Script, ComponentGraph, TypeResolver, Oracle/*), `src/Ts/**` + `src/Ts/Expr/**` (the JS-expression lexer/Pratt parser lives at `src/Ts/Expr/*`, not `src/Vue/Expr/*` — that path does not exist), `src/SyntaxNode.php`/`src/SyntaxExpression.php`, and every detector under `src/Detectors/Frontend/**` (including `src/Detectors/Frontend/TypeScript/**`, which is where the TS-specific detectors live — there is no separate top-level `Ts` detector namespace).

---

## 1. Node kinds

### (a) Template nodes — `src/Vue/Element.php`, `src/Vue/Tokenizer.php`

All template nodes are one class, `Element`, distinguished by a `#`-prefixed synthetic tag or a real tag name (`Element.php:16-47`, `135-168`).

| kind | tag sentinel | fields read | example consumer |
|---|---|---|---|
| root/fragment | `#root` | `children` | `Element::isRoot` (Element.php:151-154); `Sfc::parse` fallback (Sfc.php:76) |
| element | real tag (`div`, `Dialog`…) | `tag`, `attributes` (name→value\|null map), `children`, `line`, `start`/`end` byte span, `attributeSpans` | `Element::isElement/isComponent` (Element.php:165-168, 503-506) |
| text | `#text` | `text` (raw, un-decoded), `start`/`end` | `Element::isStaticText` (Element.php:144-149); `Codebase::whereText` (Codebase.php:233-236) |
| interpolation | not a node — a substring of a `#text`'s `text`, extracted on demand | `{{ … }}` body string | `Interpolation::extract` (Ts/Expr/Interpolation.php:17-40), consumed by `Element::isStaticText`/`expressions` (Element.php:144-149, 417-423) |
| comment | `#comment` | `text` (trimmed of surrounding whitespace — leading/trailing space inside `<!--  -->` is lost) | `Tokenizer` (Tokenizer.php:55-73); read only by fixture tooling, `CommentMarkerVerifier` (Testing/CommentMarkerVerifier.php:58-83), `DeclarationMarkers::inTemplate` (Testing/DeclarationMarkers.php:99-120) — **no production detector reads comment text** |
| attribute | n/a (part of `Element.attributes`) | name, value (`string\|null`; `null` = valueless like `v-else`/`disabled`), byte span | `Attribute` value object (Vue/Attribute.php); `Element::attribute/hasAttribute/namedAttribute` (Element.php:170-200) |
| directive | n/a (an attribute whose name matches `Directive` conventions) | name (`v-if`, `v-for`, `v-model[:arg][.modifier]`…), value = raw JS expression string | `Directive` enum (Vue/Directive.php); `Element::directiveBindings` (Element.php:282-298), `propBindings`/`eventBindings` (Element.php:309-355) |

Directive **arg** and **modifiers are not parsed into structured fields anywhere** — they live only inside the raw attribute-name string, recovered by ad-hoc `str_starts_with`/suffix checks: `Element::directiveBindings` matches `"{$prefix}:"`/`"{$prefix}."` (Element.php:282-298); `propBindings`/`eventBindings` special-case `:`/`v-bind:`/`@`/`v-on:` (Element.php:309-355); `isContextBound` string-matches `#name`/`v-slot`/`v-slot:` for slots (Element.php:452-469). **This is a real gap the new schema should close** — see §8.

SFC top-level blocks are a separate node kind, `Block` (Vue/Block.php:16-53): `tag` (`script`/`template`/`style`), raw `attributes` text parsed the same way as element attributes (so `setup`, `lang="ts"`, `scoped` are attributes), `content`, 1-based `line`, byte `start` of content. `Sfc::parse` builds the list (Sfc.php:27-77) and finds `<!-- -->` outside blocks by byte scan (Sfc.php:41-46, comments here are simply skipped, not modeled).

### (b) JS/TS expression nodes — `src/Ts/Expr/Expr.php`, `ExprKind.php`

One class `Expr` tagged by a closed enum `ExprKind` (Ts/Expr/ExprKind.php:13-63), each carrying an untyped `array<string,mixed> $props` map (`ExpressionTree` trait, ExpressionTree.php:15-81).

| kind | props read | example consumer |
|---|---|---|
| `identifier` | `name` | `Expr::isThis` (Expr.php:237-240), `roots()` (Expr.php:304-305) |
| `literal` | `raw` (source text), `value` (unquoted for strings) | `literalType()`/`inferType()` (Expr.php:493-540); `isNullLiteral`/`isBlankLiteral` (Expr.php:61-64, 119-124) |
| `member` (`a.b`, `a?.b`) | `object` (Expr), `property` (string), `optional` (bool) | `asChain()` (Expr.php:383-397); `IndexAsKeyDetector` (Frontend/IndexAsKeyDetector.php:35-52) |
| `index` (`a[b]`) | `object`, `index` (both Expr) | `memberDepth()` (Expr.php:278-292) |
| `call` | `callee` (Expr), `arguments` (list<Expr>), `optional` (bool, optional-call chains) | `Element::eventBindings` (Element.php:344-355); `asCall()`/`callee()` (Expr.php:413-484); `PropDrillingDetector` (Frontend/PropDrillingDetector.php:98-112) |
| `unary` (`!a`,`-a`,`+a`,`typeof`,`await`,`new`,…) | `op`, `argument` | `inferType()` (Expr.php:525-529) |
| `binary` (`a===b`,`a\|\|b`,`a+b`,…) | `op`, `left`, `right` | `isCoalesce`/`isNullComparison`/`isShortCircuit` (Expr.php:86-112, 191-195) |
| `conditional` (`a?b:c`) | `test`, `then`, `else` | `isTernary`/`isNestedTernary` (Expr.php:173-186) |
| `array` (`[…]`) | `elements` (list<Expr>) | `arrayType()` (Expr.php:547-568) |
| `object` (`{…}`) | `keys` (list<string\|null>), `values` (list<Expr>) | `objectEntries()`/`objectShape()` (Expr.php:440-453, 628-647); `ViteAliases` (Vue/ViteAliases.php:63) |
| `arrow` (`(…)=>…`) | `params`, `body` (Expr, expression-bodied) or `block` (BlockStmt, block-bodied) | `Expr::blocks()` (Expr.php:38-49); `Script::reactiveType` (Vue/Script.php:70-89) |
| `for` (`v-for` head) | `aliases` (list<string>), `iterable` (Expr) | `Element::loop/loopVars/loopIterable` (Element.php:237-272); `IndexAsKeyDetector` (Frontend/IndexAsKeyDetector.php:41-52) |
| `assign` (`target = value`, event-handler write) | `target`, `value` | `PropMutationDetector` (Frontend/PropMutationDetector.php:65-70); `Boundary::models` (Vue/Boundary.php:395-411) |
| `unknown` | none (fallback for unrecognised syntax) | degrade-gracefully sentinel, e.g. `Expr::child` (Expr.php:738-743) |

### (c) TS statement/declaration nodes — `src/Ts/Node/*.php`

Every node extends abstract `Node implements SyntaxNode` (Ts/Node/Node.php:19-…), which supplies `children()`, `nested()` (children + arrow callback blocks it holds via `expressions()->blocks()`, Node.php:44-52), `descendants()`, `expressions()`, `variant()`, `declaredNames()`, `functionBody()`.

| kind | fields/children read | example consumer |
|---|---|---|
| `Module` | `imports` (list<ImportDecl>), `body` (list<Node>) | `Codebase::collectModules`/`tsNodes` (Vue/Codebase.php:461-521); `Module::call/typeDeclaration/localTypes/localNames` (Ts/Node/Module.php) |
| `ImportDecl` | `bindings` (local→imported/`default`/`*`/alias), `source`, `typeOnly` | `Script::importSpecifier/reExports` (Vue/Script.php:106-115, 364-388); `ImportStatement::of` (Vue/ImportStatement.php) |
| `ClassDecl` | `name`, `members` (MethodDecl\|FieldDecl), `header`, `abstract` | `Codebase::whereClass` (Vue/Codebase.php:324-327); `ExprMatch::ownField` via `enclosingClass` (Ts/ExprMatch.php:33-38) |
| `FieldDecl` | `name`, `type`, `modifiers`, `initializer`, `optional` | `FalselyOptionalFieldDetector` (Frontend/TypeScript/FalselyOptionalFieldDetector.php:24-31); `DefendedCertainFieldDetector` (Frontend/TypeScript/DefendedCertainFieldDetector.php:24-31); `isOptional()` (Ts/Node/FieldDecl.php:31-36) |
| `MethodDecl` | `name`, `params`, `returnType`, `modifiers`, `body`, `accessor` | `NodeMatch::isConstructorDeclaration` (Ts/NodeMatch.php:67-70) |
| `FunctionDecl` | `name`, `params`, `returnType`, `returnObject` (name-based `return {…}` shape), `bodySource`, `body` | `Script::declaredType/returnTypeName/inferredReturnFields` (Vue/Script.php:445-519, 193-215) |
| `VariableDecl` | `keyword`, `pattern`, `typeAnnotation`, `initRaw`, `initCall`, `initParams`/`initReturnType` (arrow signature), `initializer` | `Script::propsVariable/emitName/staticConst/destructuredCall` (Vue/Script.php:297-337, 482-505) |
| `Pattern` (`NamePattern`/`ObjectPattern`/`ArrayPattern`) | bound names; `ObjectPattern.entries` maps local→source key, `.rest` | `Script::assignedFrom` (Vue/Script.php:318-327); `PropTypes::composableType` via `destructuredCall` (Vue/PropTypes.php:119-141) |
| `InterfaceDecl` / `TypeAliasDecl` | `name`, `members`/`type`, `header`, `.fields()`, `.references()` | `Script::typeFields/localTypes` (Vue/Script.php:166-182); `TypeResolver::resolve` (Vue/TypeResolver.php:28-61) |
| `CallExpr` (macro/composable call, distinct from `Expr::Call`) | `callee`, `typeArguments` (TypeNode[]), `arguments` (raw source), `expression` | `Script::definePropsCall/reactiveType` (Vue/Script.php:427-436, 70-89) |
| `BlockStmt` | `body` (list<Node>) | `CatchClause::isSwallowedCatch` (Ts/Node/CatchClause.php:26-31) |
| `IfStmt` | `test` (Expr), `then`, `otherwise` (else-if chained as nested IfStmt) | guard-clause / branching rules via `isBranchingConstruct`/`isGuardClause` |
| `SwitchStmt` / `SwitchCase` | `subject`, `cases` (`test`\|null for default, `body`) | `Codebase::whereSwitch` (Vue/Codebase.php:372-375); `SwitchStmt::hasDefault` (Ts/Node/SwitchStmt.php) |
| `LoopStmt` | `keyword` (`for`\|`for-of`\|`for-in`\|`while`\|`do`), `head` (list<Expr>), `body` | `isLoop`/`isBranchingConstruct` (Ts/Node/LoopStmt.php) |
| `TryStmt` / `CatchClause` | `body`, `catch` (parameter + body), `finally` | `isSwallowedCatch` (Ts/Node/CatchClause.php:26-31) |
| `ReturnStmt` | `value` (nullable Expr) | `returnsAbsence()` (Ts/Node/ReturnStmt.php:30-36) |
| `ThrowStmt` | `value` | `isThrow()` |
| `JumpStmt` | `keyword` (`break`\|`continue`), `label` | — |
| `LabelledStmt` | `label`, `body` | — |
| `ExprStmt` | `expr` | `isExpressionStatement()` |
| Types: `KeywordType`, `NamedType` (+generics, Vue reactive-wrapper unwrap list), `CompositeType` (union/intersection), `ArrayType`, `TupleType`, `ObjectType`, `FunctionType`, `IndexedAccessType`, `TypeofType`, `ParenType`, `VerbatimType` (fallback: raw bracket-balanced source for anything unmodelled — conditional/mapped/template-literal types, `keyof`) | `.render()`, `.references()` (named-type dependency list), `.admitsAbsence()`, `.unwrapRef()`, `.fieldsWith()` | `Script::declaredType/propTypes/fieldType` (Vue/Script.php); `TypeResolver` |

---

## 2. Per-node scalar facts

- **Tag name**: `Element.tag`, raw case as written; PascalCase-first-letter test decides "is a component" (`ctype_upper($tag[0])`, Element.php:503-506); lowercase compare for HTML tags (`strtolower`, e.g. Query.php:57-60, Boundary.php:23,137).
- **Static vs bound attribute**: decided by NAME prefix only — `:`, `@`, `v-` (`Element::isBindingName`, Element.php:383-388). A plain `class="x"` is static; `:class="x"`/`v-bind:class` is bound. No structural "is-directive" flag exists beyond this string test.
- **Directive name/arg/modifiers**: `Directive` enum of the 11 built-ins (Vue/Directive.php:13-26); arg/modifier are NOT split out (see §1a) — carried inline in the attribute key (`v-model:title.lazy`).
- **Directive value**: raw JS source string per attribute (`Element.attributes[name]`); parsed lazily via `Ts\Expr\Parser::parse()` per call site, never cached on the `Element` (re-parsed every time `binding()`/`propBindings()`/`eventBindings()`/`expressions()` is called — Element.php:394-426).
- **Operators**: JS binary/unary ops as raw strings (`===`,`!==`,`==`,`!=`,`&&`,`||`,`??`,arith,`instanceof`,`in`) enumerated once in `Ts\Token` (Token.php, `EQUALITY`/`SHORT_CIRCUIT` const arrays) and read off `Expr.get('op')`.
- **Literal values**: `Expr.props['raw']` (exact source spelling, e.g. `'x'` vs `"x"`), `.props['value']` (unquoted for strings); `LiteralType` enum (`boolean`/`null`/`undefined`/`string`/`number`) computed from `raw` (Expr.php:493-510).
- **Identifiers**: `Expr.props['name']`; `this` detected by name-equality (`Expr::isThis`, Expr.php:237-240) — no separate `ThisExpression` kind.
- **SFC blocks**: `Block.tag` (`script`/`template`/`style`), `Block.attributes` (`setup`, `lang`, `scoped`, arbitrary — parsed with the same attribute lexer as elements, Block.php:26-36), `Block.line`, `Block.start`. `Sfc::order()` exposes block tags in document order for a script-after-template rule (Sfc.php:167-170). Multiple `<script>` blocks are concatenated with `"\n"` for reading (`Sfc::scriptContent`, Sfc.php:121-132); the *setup* script's start is found via `hasAttribute('setup')` (Sfc.php:150-159).
- **Component-ness**: `Element::isComponent()` = PascalCase tag (Element.php:503-506) — no cross-reference to an actual import/registration is required for this predicate (that's `ComponentGraph`'s job, §6).
- **Optional-chain flag**: `Expr.props['optional'] === true` on `Member`/`Index`/`Call` (`isOptionalChain`, Expr.php:201-204).
- **Field modifiers** (TS): `Modifiers` value object over a `list<string>` of raw keywords (`public`/`protected`/`private`/`static`/`readonly`/`abstract`/`override`/`declare`/`async`); visibility defaults to `public` when unwritten (Ts/Modifiers.php:56-69).

---

## 3. Spans & positions

- Every position in this engine is a **byte offset** (PHP `strlen`/`substr`/`strpos`, not multibyte-safe) — see `Positioned` trait (`Positioned.php:12-36`: "`[start, end)` byte range … 0/0 for something built by hand"), `Span` (`Span.php:8-14`: "the `[start, end)` byte range … end exclusive"), and `Tokenizer`'s use of `strlen`/`substr` throughout (Tokenizer.php).
- Ranges are **half-open, end-exclusive**: `[start, end)`. An *inclusive* variant exists only as `Span::slice($source,$start,$endInclusive)` for a different, php-parser-adapted use (`Span.php:44-48`) — not used by the Vue/TS engines.
- **Element/attribute spans** are absolute byte offsets into the *whole `.vue` file* from the start: `Tokenizer::tokenize($html, $lineOffset, $byteOffset)` is handed the template's line/byte offset within the SFC (`Sfc::parse`, Sfc.php:65-73) and every emitted node adds `$this->byteOffset`/`$this->lineOffset` (Tokenizer.php:37-39, 60-68, 109-146, 190-220). `Attributes::scan` returns spans relative to the *attribute-text* substring; `Tokenizer::spansOf` shifts them to file-absolute (Tokenizer.php:154-163).
- **Line numbers** are 1-based, computed as `substr_count($source, "\n", 0, $offset) + 1` (`Span::lineAt`, Span.php:126-129; also inlined in `Tokenizer`/`Sfc`).
- **TS/script node spans**: `Ts\Parser`/`ModuleFile` thread a `$base` offset — the byte offset the script's *content* begins at within the `.vue` source (`ModuleFile::fromBlock($block, $file, $source, $blockStart)`, ModuleFile.php:58-61; `Ts\Parser::module($source, $base)`; `Ts/Parser.php:1394` passes `$this->base + $start` into the expression parser). So a TS node's `start`/`end` are absolute offsets into the *whole `.vue` file* (or the `.ts` file, base 0), and `ModuleFile::lineAt`/`spanAt` (ModuleFile.php:169-177) turn those into `file:line`/`Span` directly off `expr->start`/`expr->end` — confirmed used live in `ExprMatch::line/span` (Ts/ExprMatch.php:40-58) and `NodeMatch::line/span` (Ts/NodeMatch.php:37-39, 85-88).
- **Template-binding expression spans are NOT mapped back to the file.** `Element::binding()`/`propBindings()`/`eventBindings()`/`expressions()`/`loop()` all call `Ts\Expr\Parser::parse($value)` with **no base offset** (Element.php:239, 329, 350, 398, 413, 420 — grep confirms every call site in `Vue/Element.php`, `Vue/SwitchCaseChain.php:102`, `Vue/Boundary.php:398`, `Vue/Script.php:86,472`, `Vue/ViteAliases.php:63,108` omits the offset). The resulting `Expr::start/end` are therefore relative to the *attribute-value substring*, not the SFC — meaningless as a file position. No detector currently reads `Expr::start`/`Expr::end` directly for a template binding (grep confirmed); every frontend "where is this" answer for a template finding comes from the **enclosing `ElementMatch`** instead (`ElementMatch::span()`/`line()`/`location()`, ElementMatch.php:48-61, using `Element.start`/`end`/`line`, which ARE file-absolute). **This is a real asymmetry between the Vue and TS engines that the new schema must decide about explicitly** (§8).
- `ElementMatch::span()` builds a `Span` from the *component's* `sfc->source`/`start`/`end` (ElementMatch.php:48-51); `SwitchCaseChain::span()` spans from the head element's `<` to the tail branch's `>` across several elements (SwitchCaseChain.php:36-41).
- Comment node span: `[position of '<!--', position past '-->')`, text between is `trim()`-med, so leading/trailing whitespace inside the comment is lost at parse time (Tokenizer.php:55-68).

---

## 4. Comments & docblocks

- **Vue template comments** (`<!-- … -->`) *are* modelled — as `#comment` `Element` nodes with trimmed text and a real file-absolute span (Tokenizer.php:55-68). They are read only by test/fixture infrastructure:
  - `CommentMarkerVerifier::collectMarked` walks siblings looking for `@sin\s+(\w+)` in a comment immediately preceding an element (Testing/CommentMarkerVerifier.php:58-83) — this is exactly the `<!-- @sin Name -->` fixture convention named in the ticket.
  - `DeclarationMarkers::inTemplate` reads the same convention for any `@tag Name` (`@sin`/`@fixed`/`@righteous`/`@example`) (Testing/DeclarationMarkers.php:99-120).
  - **No production Frontend detector reads template comment text.**
- **JS/TS comments** (`//`, `/* … */`) inside `<script>`/`.ts` are **entirely discarded by the lexer** — `Ts\Lexer::skipLineComment`/`skipBlockComment` (Ts/Lexer.php:21-41) consume and drop them; they never become tokens, nodes, or trivia attached to a node. There is **no JSDoc representation anywhere** in the TS engine (no docblock node kind, no "leading comment" field on any `Ts\Node`).
- The `// @sin Name` fixture convention for `.ts` files (`Testing/DeclarationMarkers.php`, and `Language::isCommentLine`) is read by **re-scanning the raw file text line-by-line with `file_get_contents`**, entirely bypassing the parsed AST (`DeclarationMarkers::lines`/`markersAbove`, DeclarationMarkers.php:149-186) — because the lexer throws the comments away, this out-of-band text scan is the *only* way the marker survives.
- **Backend equivalents** (`BloatedDocblockDetector`, `CeremonyDocblockDetector`, etc.) have **no frontend counterpart** — confirms the schema currently has zero comment/docblock carrying capacity for TS, which is a gap if any future frontend "documentation" rule is wanted.

---

## 5. Types

- **Declared TS types are read structurally**, never regexed: `TypeNode` subclasses (§1c) with `.render()` (re-emit exact TS), `.references()` (transitive named-type dependencies — used to carry a parent-local `interface`/`type` into an extracted child, `Module::localTypes`, Ts/Node/Module.php), `.admitsAbsence()` (nullable check), `.unwrapRef()` (Vue reactivity unwrap: `Ref`/`ComputedRef`/`ShallowRef`/`WritableComputedRef`/`MaybeRef`/`MaybeRefOrGetter`, NamedType.php `WRAPPERS` const), `.fieldsWith()`.
- **Props**: `Script::propTypes()` reads `defineProps<{…}>()` (direct or wrapped in `withDefaults(...)`) as a `name=>renderedType` map, resolving a named type via `typeFields()` when the generic argument is a bare name rather than an inline object (Vue/Script.php:405-436).
- **Emits**: only the *binding name* is read (`Script::emitName()`, Vue/Script.php:334-337) — the *event names/payload types* declared in `defineEmits<{...}>()` are **not modelled at all**; `Boundary::emits()` invents its own emit contract (name→arity) purely from observed handler calls, independent of any `defineEmits` declaration (Vue/Boundary.php:494-566).
- **Declaration-space field lists lose their types**: `Script::declarations()`/`TypeDeclaration` record only field **names** (`array_keys($this->readFields($bodyAt))`, Script.php:252-259; `TypeDeclaration.fields: list<string>`, Vue/TypeDeclaration.php:13-23) — used by `MirroredServerTypeDetector`, which therefore compares a hand-written TS type against a backend Spatie Data contract **by field-name set only, not by field type** (`TypeDeclarationMatch::fields()`/`fieldCount()`, Vue/TypeDeclarationMatch.php:26-34; `MirroredServerTypeDetector::mirrorsAServerContract`, Frontend/MirroredServerTypeDetector.php:79-90). Field *types* (name→type) are available elsewhere via `Script::typeFields()` (Vue/Script.php:166-169) but that finer-grained map is not what the mirror detector uses.
- **Resolved/inferred types**, three tiers, all read by the current engine:
  1. **Sound AST inference** (`Expr::inferType()`/`literalType()`/`returnType()`, Expr.php:493-618) — a literal, comparison, arithmetic, homogeneous array, or agreeing ternary/logical branches. Never guesses.
  2. **Cross-file structural resolution** (`TypeResolver::fields`, Vue/TypeResolver.php:19-61; `PropTypes::typeOf`, Vue/PropTypes.php:36-141) — follows imports/re-exports and the `ComponentGraph` to type a prop from wherever its value actually originates (parent binding → parent's own prop → …, cycle-guarded), and traces a `const { x } = useComposable()` destructure into the composable's own declared or *inferred* return shape (`Script::inferredReturnFields`, re-scoping a fresh `Script` over the composable's body source, Vue/Script.php:193-215). **All computed in PHP — no external tool involved.**
  3. **External checker (`vue-tsc`) as a last resort** — see §7; only reached when tiers 1–2 leave a local `unknown`.
- **Type node kinds recognised**: keyword, named+generic, union/intersection, array, tuple, object literal, function, indexed-access (`T['field']` — used to build top-down prop types), `typeof`, parenthesised, and a **verbatim fallback** for anything unmodelled (conditional types, mapped types, template-literal types, `keyof`/`readonly` operators) that is captured as raw balanced source and never mis-parsed (`VerbatimType`, Ts/Node/VerbatimType.php).

---

## 6. Symbols & call targets

- **Imports**: `ImportDecl` (`bindings`: local→imported-name/`default`/`*`/alias-path, `source`, `typeOnly`) parsed structurally (`Script::imports()`, Vue/Script.php:96-99); `Script::importSpecifier($name)` finds which module a name came from (Vue/Script.php:106-115); `Script::reExports()` follows barrel `export … from '…'` (Vue/Script.php:364-388).
- **Module resolution** (bare/relative/aliased specifier → real file path) is done **entirely in PHP**, mimicking a bundler: `ModuleResolver` walks up to the nearest `vite.config.*`/`package.json` as project root, discovers path aliases from the Vite config once per root (`ModuleResolver::forFile`, Vue/ModuleResolver.php:53-58; alias discovery in `ViteAliases::discover`, Vue/ViteAliases.php), then tries `.ts`/`.tsx`/`.vue`/`.js`/`/index.*` extensions (Vue/ModuleResolver.php:15, 111-128). This is a **whole-program, PHP-computed** fact — no tsconfig `paths` are read, only Vite config aliases, so a project whose aliases live only in `tsconfig.json` will silently fail to resolve (worth flagging to the schema designer, though out of strict scope here).
- **Component registration/resolution**: `ComponentGraph::of($codebase)` builds the render tree by, for every PascalCase element in every template, resolving its tag to an imported module via `Script::importSpecifier($tag)` + `ModuleResolver` (Vue/ComponentGraph.php:28-57). **No `components: {…}` options-API registration or global `app.component()` registration is read at all** — resolution is import-based only, so an Options-API or globally-registered component's usages are invisible to the graph. `ComponentGraph::usagesOf($file)` gives the reverse edges (who renders me, with what props) — the fan-in half of the prop-typing climb (Vue/ComponentGraph.php:64-69).
- **Which component a tag refers to** is *only* answered indirectly, per detector, by re-doing `Script::importSpecifier` + `ModuleResolver::resolve` (e.g. `PropDrillingDetector::resolveChild`, Frontend/PropDrillingDetector.php:157-166; `IndexAsKeyDetector::iteratesArray`, Frontend/IndexAsKeyDetector.php:58-73) — there is no cached "tag → resolved file" map on `Element`/`ElementMatch` itself.
- **Calls resolving to functions/composables**: `Expr::calledFunctions()`/`callee()`/`asCall()` (Expr.php:334-484) name only *bare-identifier* callees — a method call (`form.post()`) is not counted as "calling a function", only its *receiver* counts as a data root (`Expr::roots()`, Expr.php:302-323, and `gatherChains`, Expr.php:652-684, explicitly treat a call's receiver as data and its method name as not-a-field). Composable tracing is multi-hop and PHP-computed: `Script::destructuredCall` (which composable a destructured local came from) → `importSpecifier` → `ModuleResolver` → the composable's own `Script::returnTypeName`/`inferredReturnFields` (Vue/Script.php:500-519, 193-215; orchestrated in `PropTypes::composableType`, Vue/PropTypes.php:119-141).
- **Prop-forwarding / conduit graph** (used by `PropDrillingDetector`): a prop is a "conduit" only when every read of it is a bare pass-through to exactly one child prop, verified against script-local shadowing (`localNames()`) and `props.x` member reads (`accessesMember`) (Vue/PropDrillingDetector.php:83-134).
- **Reactive-root tracking**: `v-model` targets across a component are collected as "reactive roots" so a deep-reach cluster does not flag two-way-bound local state as a server-shaped nested object (`DeepReachCluster::reactiveRoots`, Frontend/DeepReachCluster.php:118-134).

---

## 7. Whole-program facts

| fact | computed where | bridge or PHP? |
|---|---|---|
| Import graph / module resolution | `ModuleResolver` + `ViteAliases` (Vue/ModuleResolver.php, Vue/ViteAliases.php) | **PHP**, mimics bundler resolution off Vite config only |
| Component render tree (`ComponentGraph`) | `ComponentGraph::of` (Vue/ComponentGraph.php:28-57) | **PHP** — one pass over every component's imports + element tags |
| Top-down prop typing across components (`PropTypes`) | Vue/PropTypes.php | **PHP**, cycle-guarded recursive climb |
| Cross-file type resolution (imports/re-exports/barrels) | `TypeResolver::resolve` (Vue/TypeResolver.php:28-61) | **PHP** |
| Duplicate/near-duplicate element & function detection across the whole codebase | `DuplicateElementDetector`/`NearDuplicateElementDetector`/`DuplicateFunctionDetector`/`NearDuplicateFunctionDetector` (Frontend/*.php) via `structureHash`/`shapeSignature`/`StructuralHash` (Ts/StructuralHash.php, SyntaxHash.php) | **PHP**, whole-codebase grouping |
| Server-type mirroring (`MirroredServerTypeDetector`) | cross-*language* fact: compares a TS `interface`/`type` against a **backend PHP Spatie Data contract**, fed in via a `Contracts`/`TypeContract`/`GeneratedTypes` bridge (`ConsumesContracts`, Frontend/MirroredServerTypeDetector.php:14-90) | **Bridge** — the backend engine's own type facts are injected as pre-computed `Contracts`, not derived by the frontend engine itself |
| Page-root discovery (Inertia glob) | `PageRoots::discover` (Vue/PageRoots.php) | **PHP**, reads `app.ts`'s `import.meta.glob(...)` call structurally |
| Component-library reuse fingerprinting (scribe-side, not detection) | `ComponentLibrary` (Vue/ComponentLibrary.php) | **PHP**, whole-codebase |
| **Type-checker-resolved types** (last resort for a still-`unknown` local) | `VueTscOracle`/`TscDiagnostics`/`TypeProbe` (Vue/Oracle/*.php) | **External bridge — real `vue-tsc`**, the project's own (not a package dependency). The default is `NullTypeOracle` (resolves nothing) so the engine never *requires* a checker (Oracle/NullTypeOracle.php:11-17; `TypeOracle` interface doc, Oracle/TypeOracle.php:9-15). |

**How the `vue-tsc` bridge works** (the one place TS type info comes from an external tool rather than PHP): `VueTscOracle::locate` walks up from the scan path to find a project shipping `node_modules/.bin/vue-tsc` (Oracle/VueTscOracle.php:29-48). For a batch of `{component, stillUnknownLocalNames}` queries it writes a **probe copy** of each `.vue` file with an appended, impossible-type assignment per unresolved local (`const __cc_x: __CcNo_x = x;` — `TypeProbe::probes`, Oracle/TypeProbe.php:53-68), runs `vue-tsc --noEmit --skipLibCheck --pretty false --noErrorTruncation --incremental` **once for the whole batch** (Oracle/VueTscOracle.php:50-71, 118-128), then parses the `TS2322 "is not assignable to type '__CcNo_x'"` diagnostics back into a `name=>type` map by string-scanning stdout (`TscDiagnostics::types`, Oracle/TscDiagnostics.php:27-48), dropping `unknown`/`any`/empty/>200-char results (Oracle/TscDiagnostics.php:50-59). Probe files are deleted in a `finally` (Oracle/VueTscOracle.php:58-62). This is genuinely a **compiler-truth bridge**, structurally analogous to what a Go engine's language-server bridge would need to emit — the schema should reserve a slot for "type resolved by an external checker" distinct from "type inferred/declared by the AST reader."

---

## 8. Must-carry list

Tagged `[syntax]` (recoverable from source text alone), `[semantic-local]` (needs cross-referencing within one file/component but no other files), `[semantic-whole-program]` (needs other files, or an external tool).

### Vue (template)

- `[syntax]` Tag name, case-preserved; element vs text vs comment vs root/fragment distinction (Element.php:135-168).
- `[syntax]` Attribute list as ordered name→(value|null) pairs, plus each attribute's own byte span (for surgical removal) (Element.php:30-46, Tokenizer.php:154-163).
- `[syntax]` **Directive name, arg, and modifiers as three separate fields** (`v-model:title.lazy` → name=`model`, arg=`title`, modifiers=[`lazy`]) — currently only reconstructed ad hoc via string prefix/suffix matching (Element.php:282-355, 452-469); the schema should carry this structurally so a Go engine never re-implements the string scan.
- `[syntax]` Whether an attribute is "bound" (`:`/`@`/`v-`-prefixed) vs a static literal attribute (Element.php:383-388).
- `[syntax]` Byte span (start/end) and 1-based line for every node, file-absolute (Tokenizer.php:35-102, 128-234).
- `[syntax]` Text content, raw (not HTML-entity-decoded) — and separately, the extracted `{{ … }}` interpolation bodies within it (Interpolation.php:17-40).
- `[syntax]` Comment text, trimmed, with byte span — needed for both real detectors (none yet, but the marker convention needs it) and fixture tooling (Tokenizer.php:55-68).
- `[syntax]` SFC block list in document order: tag, raw attributes (`setup`, `lang`, `scoped`…), content, line, content-start offset (Block.php:16-53, Sfc.php:27-77, 167-170).
- `[semantic-local]` The v-for head as structured `aliases: list<string>` + `iterable: expression`, not a raw string (Element.php:237-272; `ExprKind::For`).
- `[semantic-local]` Parsed JS expression tree for every binding/interpolation (member chains, calls, literals, ternaries, assignments) — the whole `Expr`/`ExprKind` vocabulary of §1b.
- `[semantic-local]` v-if/v-else-if/v-else sibling chain association (structural — depends on document order among siblings, `Element::followingElements`, Element.php:637-647).
- `[semantic-local]` Component-ness of a tag (PascalCase) (Element.php:503-506).
- `[semantic-whole-program]` Which file a component tag resolves to (needs import graph + module resolution across the project) (ComponentGraph.php, ModuleResolver.php).
- `[semantic-whole-program]` Top-down/bottom-up prop type resolution across the component render tree (PropTypes.php, ComponentGraph.php).
- `[semantic-whole-program]` Cross-file `interface`/`type` resolution via imports/re-exports (TypeResolver.php).
- `[semantic-whole-program]` Structural/shape hashes computed over the *whole codebase* for duplicate/near-duplicate detection (need every component's tree, not just one file).
- `[semantic-whole-program]` External-checker-resolved type for a still-unknown local (vue-tsc bridge, Oracle/*.php) — this is the one fact that genuinely cannot be derived from source alone; the schema must let a bridge either emit it directly (best) or flag "ask the checker" for a later pass.
- `[semantic-whole-program]` Server-contract mirroring fact (TS type vs backend Spatie Data class) — supplied by an external bridge already, not derived by the frontend engine.

### TypeScript (script blocks & `.ts` modules)

- `[syntax]` Full statement/declaration AST per §1c — imports, class/interface/type-alias/function/variable declarations, all statement kinds, with byte spans absolute to the *file the script sits in* (not the block) (ModuleFile.php:54-61, Ts/Parser.php:1394).
- `[syntax]` Type-node AST (§1c) with `.render()`-faithful re-emission and a **verbatim/raw fallback** for anything unmodelled (conditional/mapped/template-literal types, `keyof`) — the schema must reserve an "opaque type source" leaf so nothing is ever mis-parsed or lost (VerbatimType.php).
- `[syntax]` Modifiers (visibility, static, readonly, abstract, override, declare, async) as a structured set, with TypeScript's "no keyword = public" default made explicit (Ts/Modifiers.php).
- `[syntax]` Operator vocabulary (equality set, short-circuit set, assignment-operator set) as named constants, not inline strings (Ts/Token.php).
- **`[syntax]` — currently entirely absent: comments and JSDoc.** The lexer drops every `//`/`/* */` comment (Ts/Lexer.php:21-41); there is no docblock/JSDoc node kind, and the only fixture-marker convention that needs a comment (`// @sin Name`) is read by bypassing the AST and re-scanning raw file lines (DeclarationMarkers.php:149-186). If the new schema wants a frontend-documentation family of rules (mirroring the backend's `BloatedDocblockDetector` etc.), comment/JSDoc capture must be added — it does not exist to inventory today.
- `[semantic-local]` Field/parameter optionality as ONE derived fact combining the `?` token and the type admitting `null`/`undefined` (`FieldDecl::isOptional`/`Param::isOptional`, Ts/Node/FieldDecl.php:31-36, Ts/Node/Param.php:31-37) — used directly by `FalselyOptionalFieldDetector` and `DefendedCertainFieldDetector`.
- `[semantic-local]` "Own field of the enclosing class" resolution for a `this.x?.y` chain (`ExprMatch::ownField`, Ts/ExprMatch.php:33-38; `Expr::ownFieldRead`, Expr.php:221-232) — needs the enclosing `ClassDecl` carried alongside each expression (`ModuleFile::gather`, ModuleFile.php:97-110).
- `[semantic-local]` Sound (never-guessed) literal/expression type inference — literal type, comparison/logical/ternary agreement, homogeneous array — as a first-class fact distinct from a declared annotation (Expr.php:493-618).
- `[semantic-local]` Structural/shape body hashes per function (exact-body vs name/literal-blind shape) for duplicate detection within reach of one query (`bodyHash`/`shapeHash` via `StructuralHash`, Ts/StructuralHash.php).
- `[semantic-whole-program]` `defineProps<{…}>()`/`withDefaults(...)` resolution when the generic argument is a *named* type declared elsewhere (needs the same cross-file walk as Vue's type resolution) (Script.php:405-436).
- `[semantic-whole-program]` External-checker-resolved types (same vue-tsc bridge as Vue, since it types `<script setup>` locals too).

### Cross-cutting note for the schema

The clearest structural gap uncovered: **Vue template-expression sub-nodes carry no file-absolute position** (offsets are relative to the attribute-value substring only — no call site threads a base offset in, confirmed by exhaustive grep), while **TypeScript module expressions do** (via `ModuleFile`'s `$base`-threaded `Ts\Parser`/`Ts\Expr\Parser`). A single JSON schema for both must pick one of: (a) always store an expression's span relative to its *immediate host* (attribute value / script block) plus the host's own absolute span, and require every consumer to add them — the schema's job, not each bridge's; or (b) require every bridge to emit file-absolute offsets for every node, expression included, closing the gap the current engine has silently lived with.

