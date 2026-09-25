# Python engine inventory

Scope covered: `src/Py/**` (Parser, Expr/Parser, Node/*, Expr/*, TypeBridge, Types, Type, CallIndex, AttributeFlow, PackageGraph, ResourceReach, Enums, Dataclasses, TypedDicts, ConstantVocabulary, FeatureEnvy, LookupEnvy, FieldClumps, OwnStateMask, ParamResolution, CodeWords, NodeMatch, ExprMatch, ModuleFile, Query/ExprQuery, StructuralHash), `bridge/mypy/bridge.py` + `CONTRACT.md`, `src/SyntaxNode.php`/`SyntaxExpression.php`/`ExpressionTree.php`/`Positioned.php`/`Span.php`, and `src/Detectors/Python/**` (all ~70 detectors surveyed for which predicates are actually exercised).

---


## 1. Node kinds

Every statement is a PHP class extending `Py\Node\Node` (`src/Py/Node/Node.php:18`); every expression is one class `Py\Expr\Expr` tagged by `ExprKind` (`src/Py/Expr/ExprKind.php:11`) with a `props` bag (`src/Py/Expr/Expr.php:37`). `variant()` distinguishes sub-shapes sharing a class (async, operator, keyword).

### Statement / declaration nodes

| Node kind | Fields / children read | Example consumer |
|---|---|---|
| `Module` (file root) | `body: Node[]`, `comments: Comment[]` | `src/Py/Node/Module.php:18-21`; `ModuleFile::nodes()` walks `descendants()` |
| `FunctionDef` (`def`, incl. methods & nested fns) | `name`, `params: Param[]`, `body: Block`, `returns: ?Expr`, `decorators: Expr[]`, `async: bool`; `variant()`='async'\|'' | `NodeMatch::isBareStatePredicate` `src/Py/NodeMatch.php:233`; `CallIndex::resolve` `src/Py/CallIndex.php:285` |
| `ClassDef` (`class`) | `name`, `bases: Expr[]` (incl. keyword bases like `metaclass=`), `body: Block`, `decorators: Expr[]`; `isTypeDeclaration()=true` | `FieldClumps::isCoupled` `src/Py/FieldClumps.php:23`; `Enums` ctor `src/Py/Enums.php:33` |
| `Param` (one function parameter) | `name`, `kind: ''\|'*'\|'**'`, `annotation: ?Expr`, `default: ?Expr`, `keywordOnly: bool` | `CallIndex::argumentsAt` `src/Py/CallIndex.php:149-178` |
| `Block` (indented suite / same-line simple stmts) | `body: Node[]` | `Block::isTwoWayBranch` `src/Py/Node/Block.php:55`; `Block::docstring` `:43` |
| `Assign` (`a = b = value`) | `targets: Expr[]`, `value: Expr` | `ClassDef::memberValueKeys` `src/Py/Node/ClassDef.php:54-59` |
| `AnnAssign` (`x: T = v`) | `target: Expr`, `annotation: Expr`, `value: ?Expr` | `ClassDef::attributeAnnotation` `src/Py/Node/ClassDef.php:102-106` |
| `AugAssign` (`x += v`) | `target: Expr`, `operator: string`, `value: Expr`; `variant()`=operator | `Node::writtenTargets` override `src/Py/Node/AugAssign.php:30` |
| `Return_` | `value: ?Expr` | `FunctionDef::returnedValues` `src/Py/Node/FunctionDef.php:339-345` |
| `Raise` | `exception: ?Expr`, `cause: ?Expr` (the `from`) | `Raise::isGenericWithMessage` `src/Py/Node/Raise.php:55-66` → `MessageStringRaiseDetector` |
| `Import` (`import`/`from … import`) | `names: array<imported,alias>`, `module: ?string`, `level: int` (leading dots); `variant()`='import'\|'from' | `CallIndex::bindingsOf` `src/Py/CallIndex.php:431-470` |
| `IfStmt` (incl. `elif` as nested `IfStmt` in `else`) | `test: Expr`, `body: Block`, `else: IfStmt\|Block\|null` | `NodeMatch::hasRedundantElse` `src/Py/NodeMatch.php:118-127`; `chain()` `:41` |
| `ForLoop` | `target: Expr`, `iterable: Expr`, `body: Block`, `else: ?Block`, `async: bool` | `FeatureEnvy::iterates` `src/Py/FeatureEnvy.php:86-90` |
| `WhileLoop` | `test: Expr`, `body: Block`, `else: ?Block` | `NodeMatch::branchingDepth` `src/Py/NodeMatch.php:97-111` |
| `TryStmt` | `body: Block`, `handlers: ExceptHandler[]`, `else: ?Block`, `finally: ?Block` | `SwallowedExceptionDetector` via `ExceptHandler::swallows` |
| `ExceptHandler` (`except`/`except*`) | `type: ?Expr`, `name: ?string`, `body: Block`, `group: bool`; `variant()`='except'\|'except*' | `ExceptHandler::isBroad`/`swallows` `src/Py/Node/ExceptHandler.php:47-69` |
| `With` (incl. `async with`) | `contexts: Expr[]`, `targets: (?Expr)[]` (parallel, `as`-binding or null), `body: Block`, `async: bool` | `CodeWords::CONSTRUCTS` `src/Py/CodeWords.php:49` |
| `MatchStmt` | `subject: Expr`, `cases: MatchCase[]` | `NodeMatch::isMatchOnEnumValue` `src/Py/NodeMatch.php:184-194` |
| `MatchCase` (one `case pattern if guard:`) | `pattern: Expr` (patterns are parsed as expressions — class/sequence/literal patterns read as `Call`/`Tuple`/`Literal`/`Attribute`), `guard: ?Expr`, `body: Block` | `MatchCase::isWildcard`/`isAnswerAbsent` `src/Py/Node/MatchCase.php:34-47` |
| `Jump` (`break`/`continue`) | `keyword: string`; `variant()`=keyword | `Block::hasTrailingExit` via `isBailOut()` `src/Py/Node/Jump.php:19-27` |
| `Simple` (`pass`, `assert`, `del`, `global`, `nonlocal`, `type X = …` alias) | `keyword: string`, `holds: Expr[]` | `Simple::globalNames` `src/Py/Node/Simple.php:44-53`; alias built at `src/Py/Parser.php:570-578` |
| `ExprStmt` (expression statement / docstring) | `value: Expr` | `ExprStmt::isBareString` `src/Py/Node/ExprStmt.php:35-38` (feeds `Block::docstring`) |

### Expression kinds (`ExprKind`, one PHP class `Expr` with `props`)

| Expr kind | Props | Example consumer |
|---|---|---|
| `Name` | `name: string` | `Expr::dottedName` `src/Py/Expr/Expr.php:89-98` |
| `Literal` | `type: LiteralType` (string/bytes/number/bool/none/ellipsis/format), `value: string` (as written) | `Expr::isAbsenceValue` `:76-83` |
| `FString` | `parts: Expr[]` — flattened run of `Literal(String)` text, `Literal(Format)` spec text, and nested expressions of any kind (fields), built by `FStringReader` | `FStringReader::parts` `src/Py/Expr/FStringReader.php:37-47` |
| `Attribute` (`a.b`) | `object: Expr`, `name: string` | `Expr::isOwnAttributeRead` `:258-261` |
| `Subscript` (`a[b]`) | `object: Expr`, `index: Expr` (a `Tuple` for multi-index) | `Expr::stringKeyBase` `:134-145` |
| `Slice` (`a[lo:hi:step]`) | `lower: ?Expr`, `upper: ?Expr`, `step: ?Expr` | `src/Py/Expr/Parser.php:426` |
| `Call` | `callee: Expr`, `arguments: Expr[]` (positional / `Keyword` / `Starred` mixed) | `Expr::isConstruction` `:739-742` |
| `Keyword` (`name=value` call arg) | `name: string`, `value: Expr` | `Expr::argumentValue` `:249-252` |
| `Starred` (`*x`/`**x`) | `value: Expr`, `double: bool` | `Expr::isConditionalSpread` `:457-466` |
| `Lambda` | `params: Expr[]` (Name only — **defaults parsed then discarded**, `src/Py/Expr/Parser.php:186-212`), `body: Expr` | — |
| `Conditional` (`a if t else b`) | `test`, `then`, `else` | `Expr::defaulted` `:984-1006` |
| `Binary` | `op: string`, `left`, `right` | `Expr::isOr`/`isAnd` `:719,863` |
| `Unary` | `op: '+'\|'-'\|'~'\|'not'\|'await'`, `operand` | `Expr::isNegation` `:855-858` |
| `Compare` (chained `a<b<=c`, `is`, `is not`, `in`, `not in`) | `operators: string[]`, `operands: Expr[]` | `Expr::comparisonSubject` `:57-70` |
| `Walrus` (`n := expr`) | `target: Expr(Name)`, `value: Expr` | `src/Py/Expr/Parser.php:113-120` |
| `Tuple`/`List`/`Set` (displays) | `elements: Expr[]` | `Expr::isEmptyCollection` `:933-936` |
| `Dict` (display) | `keys: (?Expr)[]` (null entry = a `**spread`), `values: Expr[]` | `Expr::spreadsAnother`/`isJsonSchema`/`isMemberTable`/`hasFieldNameKeys` `:293-399` → `DictReturnBagDetector` |
| `Comprehension` | `of: 'generator'\|'list'\|'dict'\|'set'`, `key: ?Expr` (dict only), `element: Expr`, `clauses: ComprehensionFor[]` | `src/Py/Expr/Parser.php:768-788` |
| `ComprehensionFor` (one `for … in … if …` clause) | `target: Expr`, `iterable: Expr`, `conditions: Expr[]`, `async: bool` | `Expr::boundNames` `:379-388` |
| `Yield` | `value: ?Expr`, `from: bool` | `src/Py/Expr/Parser.php:793-800` |
| `Unknown` | (no props) — total-parser fallback for anything unparseable | `src/Py/Expr/Parser.php:815`; also standing in for mypy-only synthetic nodes the PHP parser has none for (f-string internals — CONTRACT.md) |

**Gap noted:** PEP 695 generic type parameters (`def f[T](...)`, `class C[T]:`, `type X[T] = …`) are lexed as a bracket group and **discarded** (`Parser::skipTypeParameters` `src/Py/Parser.php:263-268`) — never reach the tree. Not a must-carry today since no detector reads them, but the Go schema should decide deliberately whether to preserve or keep dropping them.

## 2. Per-node scalar facts

- **Names**: `Name.name`, `Attribute.name`, `Keyword.name`, `Param.name`, `FunctionDef.name`, `ClassDef.name`, `Simple.keyword`, `Jump.keyword`, `Import.names` (map), `MatchCase` binds via pattern names, `ExceptHandler.name` (`as e`).
- **Operators**: `Binary.op`, `Unary.op` (incl. `await` — modelled as a unary op, not its own kind), `AugAssign.operator`, `Compare.operators` (list, for chained comparisons).
- **Literal values**: `Literal.value` (raw string; escapes untouched) + `Literal.type` (`LiteralType`: String/Bytes/Number/Bool/None/Ellipsis/Format) — `src/Py/Expr/LiteralType.php:10-21`. `LiteralType` also carries semantic helpers detectors rely on: `isData()`, `isScalar()`, `isText()`, `isAbsence($value)`, `isEmptyScalar($value)` (all pure functions of type+value — Go can re-derive if it keeps the same value string).
- **Decorators**: `FunctionDef.decorators` / `ClassDef.decorators` are plain `Expr[]` (each a `Name`, `Attribute`, or `Call` for `@dataclass(...)`, `@x.setter`, etc.) — matched by `dottedName()` against fixed vocab (`property`, `classmethod`, `staticmethod`, `contextmanager`, `dataclass`, `x.setter`/`x.deleter`) in `FunctionDef.php:168-195`, `ClassDef.php:87-94`.
- **Async**: `FunctionDef.async`, `ForLoop.async`, `With.async`, `ComprehensionFor.async`, and `Unary op='await'`.
- **Keyword args**: `Keyword` expr kind (`name`,`value`); `Expr::keywordArguments()` filters a call's arguments (`Expr.php:729-732`).
- **Default values**: `Param.default` (kept); **lambda defaults are parsed and thrown away** (`Expr/Parser.php:200-202` — noted gap above).
- **Star-args**: `Param.kind` (`''`/`'*'`/`'**'`) + `Param.keywordOnly`; `Starred.double` (single vs double star) for call/display unpacking.
- **Dunder names**: no dedicated flag — detected by string test `str_starts_with('__') && str_ends_with('__')` on `FunctionDef.name` (`FunctionDef::isDunder` `:285-288`) and on assignment targets (`NodeMatch::isMemberAfterMethod` `:323`).
- **Constants**: no dedicated flag — `Assign::declaresConstant()`/`AnnAssign::declaresConstant()` derive it from naming convention (`^_*[A-Z][A-Z0-9_]*$`) or `Final`/`ClassVar` annotation (`Node/Assign.php:35`, `Node/AnnAssign.php:45-51`).
- **Relative-import level**: `Import.level` (dot count) — 0 for absolute.
- **Except-group**: `ExceptHandler.group` (`except*`).
- **F-string conversions** (`!r`/`!s`/`!a`) are folded into the `Format`-literal text alongside the `:spec`, not a separate prop (`FStringReader::field` `:102-118`).

## 3. Spans & positions

- Every `Node` and `Expr` carries `start`/`end` via the shared `Positioned` trait: `[start, end)`, **byte offsets** (PHP `strlen`/`substr`, not `mb_*` — confirmed in `Lexer.php:68,203,246`), module-relative — `src/Positioned.php:15-35`.
- `Comment.start`/`.end` are byte offsets the same way (`src/Py/Comment.php:16-17`).
- `Span` (`src/Span.php`) is the shared file+source+byte-range value object every finding is reported through; `lineAt()` converts a byte offset to a 1-based line by counting `\n` up to it (`Span.php:106-109`) — this is how `NodeMatch::line()`/`ExprMatch::line()` build `file:line`.
- **mypy span matching** (`bridge/mypy/CONTRACT.md`): the bridge emits `{start,end,...}` per resolved expression as `[start,end)` **UTF-8 byte** offsets, computed from mypy's 1-based line/column via a byte-offset line-start table (`bridge.py:104-135`) so they land on the exact same offsets the PHP parser would give the same source expression. Matching on the PHP side is a plain `"{start}:{end}"` string key per file (`Types::at` `src/Py/Types.php:29-34`, `Type Bridge::read` builds the map `src/Py/TypeBridge.php:59-67`) — **no fuzzy/nearest-span matching**; an off-by-one anywhere breaks the join silently (falls through to "untyped").
- A type mypy resolves for a synthetic node the PHP parser has none for (documented example: an f-string's internal pieces) is simply never looked up, since nothing ever queries that exact span (`CONTRACT.md`, last paragraph).
- `Types::at()` is queried only for spans of `Expr` nodes the PHP tree actually produced (attribute receivers, call arguments, general expressions) — see all call sites: `AttributeFlow.php:60`, `ExprMatch.php:214,485,512`, `LookupEnvy.php:97`, `ResourceReach.php:103`, `Codebase.php:311`, `DerivedArgumentDetector.php:136`.
- Comment-to-code attachment is span/line based too: `CommentRuns::commentsAbove()` walks backward from `lineAt(node.start) - 1` matching one comment per line that starts its own line (`src/CommentRuns.php:33-61`) — a comment that starts mid-line (trailing) is excluded from "above" reads via `startsItsLine`.

## 4. Comments & docstrings

- **`#` comments**: `Comment{text, start, end, body}` — `text` is `#`-stripped and trimmed; `body` also strips one leading `:`/space (so Sphinx `#:` field comments nest correctly) — `src/Py/Comment.php:12-29`. Collected once per module by the lexer and hung on `Module.comments` (`Parser::module` `src/Py/Parser.php:60-63`).
- **Docstrings**: not a separate node — `Block::docstring()` recognizes the block's first statement as one only if it is an `ExprStmt` holding a bare string literal (`Block.php:43-48`); `FunctionDef::docstring()`/`ClassDef::docstring()` delegate to their body block.
- **Type comments** (`# type: int`): **not read at all** — grep confirms zero handling anywhere in `src/Py` or `bridge/mypy`; the engine relies exclusively on PEP 526/3107 annotations plus mypy's own resolution. Not a must-carry unless the Go engine wants to support annotation-comment-only (pre-3.6-style) code.
- **Consumers**:
  - `ProseRule` (`src/Detectors/Python/ProseRule.php`) drives every "prose" detector off `NodeMatch::prose()` = comment-run-above joined + docstring, with Sphinx version-note blocks (`.. versionadded::` etc.) stripped first (`Docstring::withoutVersionNotes` `src/Py/Docstring.php:24-27`).
  - `NodeMatch::commentWords()` stems the comment-run text for prose-vs-code comparisons, but returns **nothing** if any comment line parses as one complete Python statement (`CommentedCode::isCode` `src/Py/CommentedCode.php:20-35`) — i.e. commented-out code never counts as narration.
  - `Docstring::onlyRestates()` parses Google/NumPy/Sphinx docstring shapes (`Args:`, `Returns:`, `:param x:`, `:type x:`, `:rtype:`) against the annotated parameter/return names to flag ceremony docblocks (`FunctionDef::hasCeremonyDocstring`).
  - `Docstring::proseParagraphs()` counts prose paragraphs before the first recognized section heading (`ClassDef::hasMultiParagraphDocstring`).
  - `Docstring::references()` extracts Sphinx cross-reference targets (`:class:`, `:func:`, `:meth:`, `:attr:`, `:mod:`, `:obj:`, `:exc:`, `:data:`, `:const:`) for `DanglingDocReferenceDetector`.

## 5. Types

All type facts come from one external source — the mypy bridge — never re-derived syntactically.

- **Declared annotations "as written"**: not from mypy — read straight off the syntax tree (`Param.annotation`, `FunctionDef.returns`, `AnnAssign.annotation`) as `Expr`, via `Expr::dottedName()`/`spelledType()` (handles a forward-reference string annotation the same as a bare name, `Expr.php:104-107`) and shape predicates: `isDictType()`, `isSequenceType()`, `isCallableType()`, `isOptionalCallableType()`, `isClassVarType()`, `optionalOf()` (unwraps `X | None` / `Optional[X]`).
- **mypy-resolved types** — one `Type` value object per resolved expression (`src/Py/Type.php`):
  - `written: string` — mypy's own rendering (`str | None`, `list[int]`) — used for display/diagnostics, not matched structurally.
  - `class: ?string` (`className()`) — the instance's class FQN when the type is (or reduces to, after stripping `None`) exactly one class instance — e.g. `shop.cart.Cart`.
  - `nullable: bool` — true iff the resolved type is a union that included `None`.
  - `constructs: ?string` (`constructedClass()`) — present only when the expression **names a class/callable type-object** (so calling it builds one), e.g. `str` in `str(x)`, `Money` in `Money.of`; distinguishes "this call builds a T" from "this call returns a T".
  - Bridge never guesses: an unresolved (`Any`) expression has **no** contract entry at all (never emits `null`) — `bridge.py:106-121`, `CONTRACT.md`.
- **Detector uses of each**:
  - `className()` → `AttributeFlow::reads` (does a receiver's mypy type match a given class, by trailing-dot suffix match, `AttributeFlow.php:60-62`); `Codebase::classesDispatchedByName` (`getattr` receiver type → `ClassDef`, `Codebase.php:311`); `ExprMatch::argumentSubjectType`/`conversionIn` (`ExprMatch.php:485,512`); `LookupEnvy::isEnumField` (`LookupEnvy.php:97-99`).
  - `constructedClass()` → `ResourceReach::classNamed` (what a call reaches, `ResourceReach.php:103-105`); `DerivedArgumentDetector::couldTakeWhole` (`DerivedArgumentDetector.php:135-138`).
  - `nullable` → not read anywhere I found a direct consumer beyond being carried on `Type`; it's exposed but no current detector queries it directly (worth confirming with git blame/future use — may be for a not-yet-written nullability rule).
  - `written` → not pattern-matched, only surfaced as-is (no detector I found parses it).
- **Whole-project typing, judged subset**: `TypeBridge::read($paths, $written)` types every module under the scanned roots (so third-party/unmodified files still inform inference) but only emits `types` lines for `$written` (the judged files) — `Codebase::types()` `src/Py/Codebase.php:155-162`; `bridge.py: typed()` filters `states()` by the `wanted` realpath set.
- **No bridge / untyped fallback**: `Codebase::types()` returns an empty `Types` (no bridge tool located, or a string-built `Codebase::fromString` with no `$roots`) — every `types()->at()` call then returns `Option::none()`, and every type-dependent predicate degrades to "unknown/false" rather than erroring (`Codebase.php:156-161`).

## 6. Symbols & call targets

- **Imports**: `Import{names, module, level}`. `Import::dottedBindings()` (`Node/Import.php:39-56`) turns one import into `alias => fully-dotted-name` for *absolute* imports only (`level===0` yields nothing — relative imports resolve structurally instead, below).
- **Module identity**: `Codebase::fullNameOf(ModuleFile)` walks up from the file through `__init__.py`-containing parent folders to build the dotted package path (`Codebase.php:344-355`); `moduleCalled($dotted)` is the reverse lookup, ambiguous names (>1 match) resolve to none (`Codebase.php:363-376`).
- **Call resolution — `CallIndex`** (`src/Py/CallIndex.php`), a full graph built once (`graph()` walks every expression in every module): resolves a callee through, in order —
  1. a `def` nested in an enclosing function (`nestedIn`, lexical scoping, nearest wins);
  2. a name the module itself declares or imports (`named`/`bindingsOf` — handles aliasing, `from x import y as z`, submodule-vs-member ambiguity by trying "declared member" then "submodule" `CallIndex.php:454-466`);
  3. `self`/`cls`/a parameter or local annotated with a class/string-forward-ref, or an attribute of `self` whose class the class body or `__init__` established (`classOf`, `classNamed`);
  4. inherited lookup through `bases` when a class doesn't declare the method itself (`methodOf`, walks first-matching base only, no MRO diamond resolution).
  - **Never guesses**: an ambiguous import, an unresolved receiver type, or a call through a type mypy could not name all resolve to `Option::none()` rather than a best guess.
  - Exposes: `callersOf(FunctionDef)`, `targetOf(Expr)`, `argumentsAt(Expr)` (maps each parameter name → the `Expr` it's bound to at one call site, handling keyword args, `self`/`cls` binding, and a `*rest` catch-all; bails to `None` on any `*`/`**`-unpacked argument), `declarationOf(FunctionDef)` (`"path:line"` — the cross-process identity key), `isOverride`/`isOverridden`/`extendsOutside` (base-class contract checks), `importsOf(ModuleFile)` (every import resolved to the `ModuleFile` it reaches, for the package graph).
- **Attribute flow — `AttributeFlow`** (`src/Py/AttributeFlow.php`): counts, across the *whole codebase*, how many reads of a given class's attribute (`self.x` inside the class, or `obj.x` where mypy typed `obj` as that class) **assume** presence (dereferenced/called/indexed without a guard) vs **acknowledge** absence (`is None`/`is not None` test, bare truthiness test, negation, `or`-fallback) — yields a `FlowVerdict{assume,guard}` a caller thresholds (e.g. "phantom nullable" = `assume>=1 && guard==0`).
- **Class hierarchy**: `ClassDef.bases: Expr[]` (raw, unresolved); `Codebase::ancestry(ClassDef)` walks bases transitively via `classNamed()`, first-declared-wins on name collision, cycle-safe (`Codebase.php:267-289`). `Codebase::classNamed($dotted)` indexes **every module's classes by short name only** (`end(explode('.', $dotted))`) — two classes sharing a short name across packages are **not** disambiguated by full path (documented limitation: "the first, when several share it", `Codebase.php:213-234`).
- **Package graph — `PackageGraph`** (`src/Py/PackageGraph.php`): packages = folders holding `__init__.py`; one `DependencyArrow` per import crossing a package boundary (`from` a module's own package `to` the imported module's package), built from `CallIndex::importsOf`. Exposes `wouldCloseACycle(referrer, target)` and `arrowsClosingAMutualPair()` (thinner-of-mutual-pair rule shared with the backend's namespace graph, `src/DependencyArrows.php:47-70`).
- **Enums / Dataclasses / TypedDicts** — closed-name registries, each built once per `Codebase`:
  - `Enums` (`src/Py/Enums.php`): fixed-point closure over class bases (direct or transitive) matching `Enum/StrEnum/IntEnum/Flag/IntFlag/ReprEnum`, **or** a decorator call argument naming one of those (`@_simple_enum(IntEnum)`); also captures each enum's member **values** as literal keys (`ClassDef::memberValueKeys`) so `holdAll($keys)` can check "do these literals exactly cover one enum's values".
  - `Dataclasses` (`src/Py/Dataclasses.php`): classes decorated `@dataclass`/`@dataclasses.dataclass` (bare or called), by name.
  - `TypedDicts` (`src/Py/TypedDicts.php`): classes basing `TypedDict`/`typing.TypedDict`/`typing_extensions.TypedDict`, by name.
  - `ConstantVocabulary` (`src/Py/ConstantVocabulary.php`): cross-codebase — which call-parameter "slots" (keyed `"file:line#paramname"` via `CallIndex::declarationOf`+param name) are ever filled with a named class constant (`Token.BRACE_OPEN`), so a raw string literal filling that same slot elsewhere can be renamed to the constant that already names it.
- **Resource reach — `ResourceReach`** (`src/Py/ResourceReach.php`): per function, the set of outside-the-project things it touches directly (own scope only, nested defs excluded) — `"fn:module.func"` for an outside call (resolved via imports or `builtins.` for an unbound single name) and `"type:module.Class"` for a class it builds/names (via mypy's `constructs`). Used for cross-codebase duplicate/"divergent twin" detection (`PythonTwinJudge`) — two functions doing the same job in different words share the same *reach*, not the same names.
- **Structural duplicate detection — `StructuralHash`** (`src/Py/StructuralHash.php` extends the shared `src/SyntaxHash.php`): formatting-blind fingerprint over `variant()+declaredNames()+expressions()+children()`; `normalized()` additionally blanks `Name` reads to `id` and blanks *data* literal values (keeps `Bool`/`None`/`Ellipsis` since those carry meaning) — purely syntactic, needs no bridge. Drives `DuplicateFunctionDetector`, `NearDuplicateFunctionDetector`, subject-ladder/guard fingerprinting (`ExprMatch::guardFingerprint`), and translate-arm matching (`Block::translates`).

## 7. Whole-program facts

| Fact | Computed how | Bridge must emit, or Go can derive from the syntax forest? |
|---|---|---|
| mypy-resolved type per expression (class/nullable/constructs) | External type checker (mypy) — genuinely semantic, not derivable from syntax alone | **Bridge must emit** (this is the one fact no engine can compute itself) |
| Call graph (`CallIndex`) | Pure name/scope/type resolution over the whole module set | **Go can derive**, provided it has: every module's tree, `Import` nodes, and `Types` for receiver spans (for type-annotated resolution) |
| Class hierarchy / ancestry | Pure name resolution over `ClassDef.bases` across modules | **Go can derive** |
| Package/import graph (`PackageGraph`) | Folder structure (`__init__.py` presence) + resolved imports | **Go can derive** (needs file-tree shape, which the bridge/schema doesn't carry today — currently computed straight off the filesystem, `Codebase.php:409-412`; the Go engine will need either filesystem access or an explicit "package roots" fact) |
| Enum / Dataclass / TypedDict membership | Base-class name matching (with decorator-argument special case for enums) across modules | **Go can derive** |
| Attribute-flow verdict (assume vs. guard counts) | Cross-module scan of every read of one class's attribute, gated by mypy type on non-`self` receivers | **Go can derive**, but depends on mypy types for the non-`self` case — so transitively needs the bridge's per-expression `class` fact |
| Resource reach / duplicate-twin detection | Own-scope call/type extraction, keyed by declaration `path:line` | **Go can derive**, transitively needs `constructs` from the bridge for the "builds a class" half |
| Structural/near-duplicate hashing | Pure syntax walk (kind/variant/props), no cross-file dependency beyond "compare all functions pairwise" | **Go can derive** entirely from the syntax tree the bridge for TS / the Go parser for Python would emit — no bridge needed |
| Constant-vocabulary (slot → named-constant classes) | Cross-codebase scan of every call's bound arguments vs. classes' string constants | **Go can derive** |
| "mtime/size-based incremental" bridge caching | `Session.checked()` in `bridge.py` reuses a mypy daemon build across `--serve` requests, invalidating only changed files | Bridge-internal implementation detail — **not a schema fact**, purely a bridge performance concern |

## 8. Must-carry list

Flat, deduplicated. `[syntax]` = read straight off one file's parse; `[semantic-local]` = derived by walking the syntax forest (possibly cross-file, but with no external oracle); `[semantic-whole-program]` = needs the mypy bridge (an external, whole-project oracle) or filesystem/package-layout information beyond the parsed text.

**Positions**
- `[syntax]` `[start, end)` byte offsets, UTF-8 bytes, half-open, on every node and every expression, module-relative.
- `[syntax]` Comment spans (`start`/`end`), same offset space, so comments can be matched to source lines independent of any node.
- `[semantic-whole-program]` mypy's per-expression span, in the *same* byte-offset space, so a reader can join by exact `(file, start, end)` — this is the bridge's whole contract; any drift breaks every type-dependent detector silently.

**Statement/declaration node shape** (per kind, see §1 table for the authoritative field list)
- `[syntax]` `FunctionDef`: name, params, body, return-annotation, decorators, async.
- `[syntax]` `ClassDef`: name, bases (incl. keyword bases), body, decorators.
- `[syntax]` `Param`: name, kind (`*`/`**`/plain), annotation, default, keyword-only flag.
- `[syntax]` `Assign`/`AnnAssign`/`AugAssign`: targets/target, annotation, value, operator.
- `[syntax]` `Import`: imported-name→alias map, `from`-module, relative-import level (dot count).
- `[syntax]` `IfStmt`/`ForLoop`/`WhileLoop`/`TryStmt`/`ExceptHandler`/`With`/`MatchStmt`/`MatchCase`: every field in §1 (test/body/else chains, handler type/name/group, context managers + as-targets, subject + cases + guard).
- `[syntax]` `Return_`/`Raise`/`Jump`/`Simple`/`ExprStmt`: value(s)/exception/cause/keyword/holds.
- `[syntax]` Docstring = first bare-string statement of a block (no separate node needed if the schema keeps statement order + literal kind).
- `[syntax]` `#` comments: text, dedented body (Sphinx `#:` aware), span; module-level list independent of the statement tree.

**Expression node shape**
- `[syntax]` Every `ExprKind` and its exact prop set from §1 (Name/Literal/FString/Attribute/Subscript/Slice/Call/Keyword/Starred/Lambda/Conditional/Binary/Unary/Compare/Walrus/Tuple/List/Set/Dict/Comprehension/ComprehensionFor/Yield/Unknown).
- `[syntax]` Literal type tag (string/bytes/number/bool/none/ellipsis/format) *and* raw value string, unescaped as written.
- `[syntax]` Operator strings for `Binary`/`Unary`/`AugAssign`, and the operator *list* for chained `Compare`.
- `[syntax]` F-string decomposition into interleaved literal-text / format-spec-text / nested-expression parts (conversions folded into the format text).
- `[syntax]` Decorators as plain expression trees (not a special "decorator" node) so `dottedName()`/call-argument matching works.

**Types (external oracle)**
- `[semantic-whole-program]` Per-expression: mypy's rendered type string, `class` (single-instance FQN, when applicable), `nullable` flag, `constructs` (FQN of the class/callable a call site *builds*, when the callee is a type object) — emitted only when resolved (never a guessed/`Any` entry).
- `[semantic-whole-program]` Which files were "written" (judged) vs merely "informing" (imported context) for a given bridge run — affects nothing about the schema shape, but the engine needs to know the judged-file set to scope its output the way the bridge's `write` parameter does.

**Symbols & call resolution**
- `[semantic-local]` Resolved call target per call site (def reached, possibly none) — including nested-function shadowing, self/cls/parameter type-based method resolution, and single-level base-class fallback (no full MRO).
- `[semantic-local]` Resolved import target per import (module file reached), for relative and absolute imports alike, including the "member vs submodule" ambiguity rule.
- `[semantic-local]` Class ancestry chain (bases resolved to declarations, first-name-wins on collisions, short-name-only matching — a known imprecision worth deciding whether to keep).
- `[semantic-local]` Enum / dataclass / TypedDict registries (name-keyed, base-class + decorator-argument matched) and each enum's member literal-value set.
- `[semantic-whole-program]` Package layout (which folders are packages via `__init__.py`) — needed for both the dotted module-name computation and the package dependency graph; currently derived straight from the filesystem, not from parsed content, so the schema/engine needs an explicit carrier for this (e.g. a manifest of package roots) if the Go engine won't have raw filesystem access.
- `[semantic-local]` Attribute-flow tallies (assume vs. guard counts) per class+attribute across the whole codebase — needs the call graph's `self` resolution plus mypy's `class` fact for non-`self` receivers.
- `[semantic-local]` Resource-reach sets per function (outside calls + constructed/named classes), used for cross-file "divergent twin" and behavioural-duplicate detection.
- `[semantic-local]` Constant-vocabulary index (call-site parameter "slots" ↔ classes whose named constants fill them).

**Whole-program / cross-file structural facts**
- `[semantic-local]` Structural-hash equality (exact and normalized) across every function in the codebase, with a minimum-body-size floor, docstrings excluded from the fingerprint — purely syntax-derived, no bridge dependency.
- `[semantic-local]` Cross-module "which file declares which top-level name" index, and "does this module bind name X at its top level" (used for shadowing/outside-name checks in resource reach).

**Known current gaps/asymmetries worth a deliberate decision in the new schema**
- PEP 695 generic type parameters (`def f[T]`, `class C[T]`, `type X[T] = …`) are parsed and discarded today — not carried.
- Lambda parameter defaults are parsed and discarded today — only the parameter names survive.
- `# type: ...` legacy type comments are never read.
- `Type.nullable` and `Type.written` are threaded through the whole stack but (as far as this survey found) have no current detector consumer — confirm before dropping them as "not must-carry", since a nullability-specific rule may be forthcoming.

