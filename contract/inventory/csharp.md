# C# engine inventory — every fact a language-neutral syntax-tree schema must carry

Scope read: `src/Cs/**`, `bridge/roslyn/**` (CONTRACT.md + TreeWriter.cs/Program.cs/Workspace.cs/Project*.cs),
`src/SyntaxNode.php`, `src/Detectors/CSharp/**`. Version emitted today: bridge contract v7
(`bridge/roslyn/TreeWriter.cs:27`, checked at `src/Cs/Bridge.php:19`).

The bridge (`roslyn-bridge`) compiles a whole solution/project with Roslyn and writes one JSON object per
file, each holding the syntax tree plus everything the compiler resolved about it
(`bridge/roslyn/CONTRACT.md`). Nothing is guessed: an unresolved fact is simply absent
(`bridge/roslyn/CONTRACT.md:43`). PHP's `Node::fromBridge` (`src/Cs/Node.php:96-124`) is the mirror-image
reader — every key it reads is a key the schema must define.

---

## 1. Node kinds

Roslyn's `SyntaxKind` name is written verbatim as `kind` (`bridge/roslyn/TreeWriter.cs:237`,
`CONTRACT.md:22`); `role` is Roslyn's own class hierarchy collapsed to five buckets — `statement`,
`expression`, `member`, `type`, `other` (`TreeWriter.cs:298-305`). Every kind below is one the C# detector
suite actually matches by name (`Node::is(...)`, `src/Cs/Node.php:125-128`); the bridge naturally emits
many more Roslyn kinds (e.g. `GotoStatement`, `LockStatement`, `CheckedStatement`, `DelegateDeclaration`,
`TrueLiteralExpression`, `VarPattern`, `RelationalPattern`, `BinaryPattern`) that no current detector reads
by name — the schema should still carry them generically (kind/role/span/children), just with no
dedicated per-kind facts required yet.

### Declarations & members (role=`member`)

| Kind | Fields/children read | Example consumer |
|---|---|---|
| `ClassDeclaration`, `StructDeclaration`, `RecordDeclaration`, `RecordStructDeclaration`, `InterfaceDeclaration`, `EnumDeclaration` | `name`, `modifiers`, `symbol`, children incl. `BaseList`, members | `Node::isTypeDeclaration` `src/Cs/Node.php:1432-1435`, `NodeMatch::enclosingType` `src/Cs/NodeMatch.php:678` |
| `MethodDeclaration`, `ConstructorDeclaration`, `DestructorDeclaration`, `OperatorDeclaration`, `ConversionOperatorDeclaration`, `PropertyDeclaration`, `IndexerDeclaration`, `LocalFunctionStatement`, `GetAccessorDeclaration`, `SetAccessorDeclaration`, `InitAccessorDeclaration`, `AddAccessorDeclaration`, `RemoveAccessorDeclaration`, `ParenthesizedLambdaExpression`, `SimpleLambdaExpression`, `AnonymousMethodExpression` | `name`, `modifiers`, `symbol`, `inherited`, body (`Block`/`ArrowExpressionClause`), `ParameterList` | `FUNCTIONS` const + `functionBody()` `src/Cs/Node.php:26-31,1832-1848` |
| `FieldDeclaration` | `modifiers`, children `VariableDeclaration`→`VariableDeclarator` | `isConstField`/`isStateMember` `src/Cs/Node.php:606,1085` |
| `EventDeclaration` | `name` (fallback name-lookup target for accessors) | `NodeMatch::name` `src/Cs/NodeMatch.php:39-46` |
| `EnumMemberDeclaration` | `name` | `Codebase::enums` `src/Cs/Codebase.php:518` |
| `AccessorList` | `children` (empty-bodied accessors ⇒ auto-property) | `Node::isStoredProperty` `src/Cs/Node.php:1114-1119` |
| `NamespaceDeclaration`, `FileScopedNamespaceDeclaration` | `symbol` → `namespaceName()` | `NamespaceGraph::walk` `src/Cs/NamespaceGraph.php:81` |

### Statements (role=`statement`)

| Kind | Fields/children read | Example consumer |
|---|---|---|
| `IfStatement`, `ForStatement`, `ForEachStatement`, `ForEachVariableStatement`, `WhileStatement`, `DoStatement`, `SwitchStatement` | children (condition/body/`ElseClause`) | `isBranchingConstruct` `src/Cs/Node.php:141` |
| `Block` | `children()` (statement list) | `swallows()` `src/Cs/Node.php:157-159` |
| `ReturnStatement`, `ThrowStatement`, `ThrowExpression`, `ContinueStatement`, `BreakStatement`, `YieldBreakStatement` | expression child | `isBailOut` `src/Cs/Node.php:1773`, `isGenericThrowWithMessage` `src/Cs/Node.php:204` |
| `ExpressionStatement` | child expression (e.g. an `AppendLine` call) | `isAppendLine` `src/Cs/Node.php:757` |
| `TryStatement`, `CatchClause`, `CatchDeclaration`, `CatchFilterClause` | `type` on `CatchDeclaration`, presence of `CatchFilterClause` | `isBroadCatch` `src/Cs/Node.php:149-156`, `isWrappingWithoutCause` `src/Cs/NodeMatch.php:255-278` |
| `LocalDeclarationStatement`, `UsingStatement` | (matched only for its "spoken words") | `CodeWords::CONSTRUCTS` `src/Cs/CodeWords.php:44,48` |

### Switch / pattern constructs

| Kind | Fields/children read | Example consumer |
|---|---|---|
| `SwitchExpression`, `SwitchExpressionArm` | arms, `DiscardPattern` arm = fallback | `namedCases`/`fallbackValue` `src/Cs/Node.php:791-846` |
| `CaseSwitchLabel`, `CasePatternSwitchLabel`, `DefaultSwitchLabel` | label's pattern/value, `WhenClause` guard | `namedCases` `src/Cs/Node.php:814`, `fallbackValue` `src/Cs/Node.php:842` |
| `WhenClause` | presence only (marks a guarded arm) | `isGuarded` `src/Cs/Node.php:828-830` |
| `IsExpression`, `IsPatternExpression` | rhs pattern/type | `isTypeCheck` `src/Cs/Node.php:1237-1241` |
| `ConstantPattern`, `DeclarationPattern`, `TypePattern`, `RecursivePattern`, `DiscardPattern`, `NotPattern`, `OrPattern` | nested pattern children, `role`=`type` parts | `isNullPattern`/`testedEnumCase`/`switchedTypes` `src/Cs/Node.php:939,730,1300-1313` |

### Expressions — calls, member access, construction

| Kind | Fields/children read | Example consumer |
|---|---|---|
| `InvocationExpression` | `target{type,name,parameters}`, `ArgumentList` | `isLookup`/`arguments` `src/Cs/Node.php:247,404-409` |
| `ObjectCreationExpression`, `ImplicitObjectCreationExpression`, `AnonymousObjectCreationExpression` | `type`, `target` (ctor), initializer children | `isGenericThrowWithMessage` `src/Cs/Node.php:206`, `constructs()` `src/Cs/NodeMatch.php:937` |
| `ArrayCreationExpression`, `ImplicitArrayCreationExpression` | trailing `...InitializerExpression` child | `writtenElements` `src/Cs/Node.php:799` |
| `ElementAccessExpression`, `ImplicitElementAccess`, `BracketedArgumentList` | receiver `type`, arguments | `isLookup`/`entryKey` `src/Cs/Node.php:384-390,879` |
| `SimpleMemberAccessExpression`, `PointerMemberAccessExpression` | `receiver`/`member` (via `parts()`), `type` | `memberName`/`isEnumCase` `src/Cs/Node.php:1338-1345,697-703` |
| `MemberBindingExpression`, `ConditionalAccessExpression` | member name, chained receiver | `parts()` `src/Cs/Node.php:1992-1993`, `NodeMatch::isBuriedThrow` `src/Cs/NodeMatch.php:404` |
| `WithExpression` | receiver + initializer | `constantChanges` `src/Cs/Node.php:1273-1290` |

### Expressions — names, literals, types

| Kind | Fields/children read | Example consumer |
|---|---|---|
| `IdentifierName`, `GenericName`, `QualifiedName`, `PredefinedType`, `NullableType`, `TupleType` | `name`, `type`, `role` | `names()`/`returnsPositionalTuple` `src/Cs/Node.php:280-282,1015-1030` |
| `ThisExpression`, `NameColon` | presence only | `names()` `src/Cs/Node.php:281`, `blankArgumentPositions` `src/Cs/Node.php:1045` |
| `NullLiteralExpression`, `DefaultLiteralExpression`, `DefaultExpression`, `FalseLiteralExpression`, `StringLiteralExpression`, `NumericLiteralExpression`, `InterpolatedStringExpression`(+`InterpolatedStringText`) | `text`, `constant`, `type` | `isAbsenceValue`/`isEmptyScalar` `src/Cs/Node.php:189-225` |
| `CastExpression`, `DeclarationExpression`, `SingleVariableDesignation` | operand, `type`, designation `name` | `conversion()` `src/Cs/Node.php:1553-1563`, `lookedUpNames` `src/Cs/NodeMatch.php:579` |
| `CollectionExpression`, `ExpressionElement`, `SpreadElement`, `ObjectInitializerExpression`, `CollectionInitializerExpression`, `ComplexElementInitializerExpression`, generic `*InitializerExpression` (suffix match) | element children | `isEmptyCollection`/`literalKeys` `src/Cs/Node.php:333-336,862-880` |
| `ParenthesizedExpression`, `ConditionalExpression` | inner/branch children | `withoutParentheses`/`fallback` `src/Cs/Node.php:311-378` |
| `CoalesceExpression`, `CoalesceAssignmentExpression`, `SuppressNullableWarningExpression` | operands, `forgivesNull` | `fallback`/`isBuriedThrow` `src/Cs/Node.php:361`, `src/Cs/NodeMatch.php:394-405` |
| `SimpleAssignmentExpression`, `AddAssignmentExpression`, `SubtractAssignmentExpression`, `PostIncrementExpression`, `PreIncrementExpression`, `PostDecrementExpression`, `PreDecrementExpression` | `operator`, target/value children, `step` | `isWrite`/`advancesACounter` `src/Cs/Node.php:585-596,890` |
| `EqualsExpression`, `NotEqualsExpression`, `LogicalAndExpression`, `LogicalOrExpression`, `LogicalNotExpression` | `operator`, operand children | `isNullTest`/`conjuncts`/`orOperands` `src/Cs/Node.php:517-534,1184-1192,725-729` |
| `TupleExpression`, `TupleElement` | slot children, `name` | `returnsPositionalTuple`/`assembledGroups` `src/Cs/Node.php:1015-1030`, `src/Cs/NodeMatch.php:1021` |
| `Argument`, `ArgumentList`, `Parameter`, `ParameterList`, `VariableDeclaration`, `VariableDeclarator`, `EqualsValueClause`, `ArrowExpressionClause`, `AttributeList`, `BaseList` | see §2 | `parameters()`/`stateTypes`/`mayFillAContract` `src/Cs/Node.php:1571,890-935,1704-1712` |

---

## 2. Per-node scalar facts

Every scalar the bridge can attach to a node, and where the PHP reader consumes it
(`Node::fromBridge`, `src/Cs/Node.php:96-124`; contract source in `TreeWriter.cs`):

| Fact | Bridge source | PHP field | Consumer |
|---|---|---|---|
| `kind` | `TreeWriter.cs:237` | `Node::$kind` | everywhere (`is()`) |
| `role` | `TreeWriter.cs:238,298-305` | `Node::$role` | `isExpression`, `whereStatement` |
| `name` | `WriteName`, `TreeWriter.cs:308-335` (declarations, identifiers, accessor names, `PredefinedType` keyword) | `Node::$name` | `memberName`, `NodeMatch::name` (falls back to the enclosing property/indexer/event for an accessor, `src/Cs/NodeMatch.php:39-46`) |
| `text` | `WriteText`, `TreeWriter.cs:338-361` (literal `ValueText`, interpolated-string text) | `Node::$text` | `isBlankString`, `stringConstants` |
| `operator` | `WriteText`, `TreeWriter.cs:348-359` (binary/assignment/prefix/postfix operator token text) | `Node::$operator` | not matched by any current C# detector directly (kept for parity with TS/Python schema) |
| `modifiers` | `WriteModifiers`, `TreeWriter.cs:363-386` (members, local functions, parameters) | `Node::$modifiers` (list) | `hasModifier`, `mayFillAContract` |
| `type`/`nullable`/`value`/`inner` | `WriteType`, `TreeWriter.cs:423-446`; attached by `WriteFacts` to typed expressions, type nodes, parameters, and `CatchDeclaration` (`TreeWriter.cs:472-499`) | `Node::$type` → `ResolvedType{name,nullable,isValueType,inner}` (`src/Cs/ResolvedType.php`) | pervasive — dictionary/JSON detection, scalar-conversion, dead-simple type checks |
| `forgivesNull` | `WriteFacts`, `TreeWriter.cs:501-504`; only when the `!`'s operand is *declared* nullable | `Node::$forgivesNull` | `NullForgivenDetector` (via `isDeclaredNullable`-equivalent semantics baked in by the bridge) |
| `step` | `WriteFacts`, `TreeWriter.cs:506-509`; marks an expression sitting in a `for`'s incrementor list | `Node::$step` | `isNonCountingFor` `src/Cs/Node.php:562-568` |
| `constant` | `WriteFacts`/`IsConstant`, `TreeWriter.cs:394-397,511-514` (compiler-folded value, enum member/const, `typeof` of a concrete type) | `Node::$constant` | `isConstant`, `isEnumCase`, `comparisonSubject` |
| `target{type,name,parameters}` | `WriteFacts`, `TreeWriter.cs:516-536`; only on invocation/creation nodes the compiler resolved | `Node::$target` → `CallTarget` (`src/Cs/CallTarget.php`) | `isLookup`, `Codebase::declarationOf/callersOf`, `NamespaceGraph::reachedFrom` |
| `symbol` | `WriteFacts`, `TreeWriter.cs:538-546`; on member declarations, fully qualified w/ parameter types, same shape as `CallTarget::symbol()` | `Node::$symbol` | `Codebase::declarationOf` (keys declarations by this string), `namespaceName()` |
| `inherited` | `WriteFacts`, `TreeWriter.cs:542-545` (`IsOverride` or interface-member match, `ImplementsInterfaceMember` `TreeWriter.cs:549-552`) | `Node::$inherited` | `isOverride`, `mayFillAContract`, `reachesOwnSignature` |
| `errors` (per file) | `Program.cs`/`TreeWriter.cs:55` (`tree.GetDiagnostics()` count of `Severity.Error`) | `WrittenFile::$errors` → `ModuleFile::$errors` | carried but **not read by any detector today** (`src/Cs/ModuleFile.php:39`) — candidate for pruning or for a future "skip broken file" policy |
| `test` (per file) | `TreeWriter.cs:57-60`, from `Project.IsTest` (`Project.cs:18`) sourced from `.csproj`'s `IsTestProject` (`ProjectFile.cs:54-58`) | `ModuleFile::isTest()` | 8 detectors reject matches in test files (e.g. `ArrayReturnBagDetector.php:32`, `DeNulledFinderDetector.php:50`) |

---

## 3. Spans & positions

- Every node carries `start`/`end` as **UTF-8 byte offsets**, `[start, end)`, trivia excluded
  (`CONTRACT.md:16`, written at `TreeWriter.cs:239-240`).
- Roslyn positions are UTF-16 code-unit offsets; the bridge converts once per file via `ByteOffsets`
  (`TreeWriter.cs:269-283`): a surrogate pair costs 4 bytes, an ASCII char 1, then 2 or 3 for wider BMP
  characters; a leading UTF-8 BOM's length is added as the starting `mark` (`MarkLength`,
  `TreeWriter.cs:286-292`) so offsets always mean "bytes into the file PHP/Go will `fread`".
  Comments reuse the same table (`WriteComments`, `TreeWriter.cs:152-153`).
- PHP never recomputes a line number itself: `Span::lineAt()` counts `\n` up to the offset
  (`src/Span.php:104-107`); `ModuleFile::lineAt`/`spanAt` (`src/Cs/ModuleFile.php:135-141`) are the sole
  entry points, used by `NodeMatch::line()`/`span()` (`src/Cs/NodeMatch.php:51-67`) and `CommentMatch::line()`
  (`src/Cs/CommentMatch.php:29-33`).
- `path` (per file) is the realpath-resolved absolute path (`Bridge::resolved`, `src/Cs/Bridge.php:66-69`);
  `Codebase::read` re-maps it back to however the caller originally spelled the path
  (`src/Cs/Codebase.php:580-591`) since the bridge always resolves symlinks.
- Parent/ancestor navigation is **not** part of the wire format — it is rebuilt once per file on the PHP
  side from `children` alone (`ModuleFile::parentIds`, `src/Cs/ModuleFile.php:150-161`), keyed by
  `spl_object_id`. A Go engine needs the same reconstruction (or the schema could carry parent links, which
  today it deliberately does not).

---

## 4. Comments & doc comments

- Comments are **not** nodes; they are a separate top-level `comments` array per file, emitted before
  `root` (`WriteComments`, `TreeWriter.cs:143-192`; `CONTRACT.md` does not yet document this array — it
  predates the last CONTRACT.md edit but is live in the v7 writer and read by `WrittenFile::fromContract`,
  `src/Cs/WrittenFile.php:26-34`).
- Each comment carries: `kind` (`line`|`block`|`doc`, from trivia kind, `CommentKind`,
  `TreeWriter.cs:227-232`), `text` (full trivia text including its `//`/`/* */`/`///` markers,
  `TreeWriter.cs:151`), `start`/`end` (byte offsets of the trivia's *full* span, `TreeWriter.cs:152-153`),
  and `code` — a heuristic asking whether the comment parses as one C# statement with punctuation code has
  (dot, paren, bracket, assignment/binary operator) rather than reading as prose (`IsCode`,
  `TreeWriter.cs:204-225`).
- A `doc` comment additionally carries a `crefs` array. For each top-level `<see cref="…"/>`-style
  reference: `text` (as written), `symbol` (fully-qualified resolved symbol, `Declared` format,
  `TreeWriter.cs:171`), `owner` (the longest qualifier that *does* resolve, when the whole cref does not,
  `TreeWriter.cs:95-118,174-176`), `ownedHere` (is that owner declared in this compilation,
  `TreeWriter.cs:179`), and `blind` (`TreeWriter.cs:180`) — see §7, this last one is whole-program.
- PHP: `Comment` (`src/Cs/Comment.php`) parses the doc comment's XML into `DocTag`s (`src/Cs/DocTag.php`)
  via `DOMDocument`, classifying each top-level element as signature-describing (`summary`, `remarks`,
  `param`, `typeparam`, `returns`, `value`) vs. everything else, and exposes `paragraphs()`,
  `restatesOnly()`, `proseLines()`. `Cref` (`src/Cs/Cref.php`) turns the four resolution facts into
  `isDangling()` (`src/Cs/Cref.php:47-56`), consumed by `DanglingDocReferenceDetector`
  (`src/Detectors/CSharp/DanglingDocReferenceDetector.php:20-23`).
- Ordinary (non-doc) comments are consumed two ways: `ProseRule` (`src/Detectors/CSharp/ProseRule.php`)
  walks every comment in every module directly (used by `ArchaeologyCommentDetector`,
  `NegativeSpaceCommentDetector`, `BloatedDocblockDetector`, `CeremonyDocblockDetector`,
  `DanglingDocReferenceDetector`); and `commentsAbove()`/`commentWords()`
  (`src/Cs/NodeMatch.php:811-829`, shared `CommentRuns` trait `src/CommentRuns.php`) attach the run of
  line/block comments standing directly above a *statement* — needs only `start` + `kind` + `text`, not
  `crefs`, and excludes documentation comments and fixture "marker" comments.

---

## 5. Types

- `type` is always: fully-qualified name (`global::Namespace.Type`, generic args included, `?` suffix for
  a nullable reference), `nullable` (annotation, not "could be missing"), `value` (present+`true` only for
  value types), `inner` (every named type nested inside a generic or array, however deep, flattened and
  de-duplicated) — `WriteType`, `TreeWriter.cs:423-446`. PHP: `ResolvedType` (`src/Cs/ResolvedType.php`),
  `namedTypes()` returns `[name, ...inner]` for anything that needs to test every type a value is "made of".
- Where `type` is attached (`WriteFacts`, `TreeWriter.cs:472-499`):
  - any expression the compiler typed, unless it sits in a type-only position (`472-483`);
  - a `TypeSyntax` in type position, but only its outermost node (skips a generic's inner type args being
    double-attached) (`485-488`);
  - a `Parameter`'s *declared* type via `GetDeclaredSymbol` (`490-493`) — separate from the expression rule
    because a parameter node itself is not an `ExpressionSyntax`;
  - a `CatchDeclaration`'s caught exception type, always non-nullable (`495-499`).
- A resolved-but-error type (`IErrorTypeSymbol`) is treated as *unresolved* and the `type` key is omitted
  entirely — never emitted as a sentinel (`472-499` guards throughout).
- PHP-side type vocabulary constants that encode **language knowledge the schema must let a C# bridge
  express, but should stay data, not schema**: `DICTIONARIES`, `JSON_OBJECTS`, `SCALARS`, `STRING`,
  `GENERIC_EXCEPTIONS` (`src/Cs/Node.php:29-56`) — all matched by *prefix/exact string on the resolved
  `type.name`*, e.g. `isLookup()` tests `str_starts_with($type, 'global::…Dictionary<')`
  (`src/Cs/Node.php:513-516,527-530`). This means the schema's `type` string format (fully-qualified,
  `global::`-prefixed, generic args left inside `<...>` rather than split into `inner` only) is
  load-bearing for detector logic that greps it — not just a display string.
- Nullability facts used beyond the raw `nullable` flag: `IsDeclaredNullable`
  (`TreeWriter.cs:404-417`, backs `forgivesNull`) checks the *declaration's* nullability (field/property/
  local/parameter/method-return), not the expression's own annotation — the schema needs both an
  expression's resolved nullability and (indirectly, via `forgivesNull`) the operand's declared
  nullability.
- Declared-type helpers on the PHP side: `Node::declaredType()`/`declaredTypeNode()`
  (`src/Cs/Node.php:970-988`) find the first `role === 'type'` child of a declaration — i.e. the schema's
  `role` classification (§1) is what lets PHP find "the type name of this property" without any C#-specific
  parsing.
- Value-type constant-choice fields (`isValueType`) matter for coupling/data-clump detectors:
  `DataClumpDetector`/`CoupledFieldsDetector` treat only scalars, `string`, records, or `isValueType` fields
  as "a value", never a collaborator reference (`src/Cs/NodeMatch.php:954-957`, `valueParamSignature`
  `src/Cs/Node.php:466-483`).

---

## 6. Symbols & call targets

- `target` = `{type, name, parameters: list<string>}` (`CONTRACT.md:32`, `WriteFacts`,
  `TreeWriter.cs:516-536`), attached only when the invocation/object-creation resolved to an `IMethodSymbol`
  — every invocation/creation is counted toward `resolution.calls`, but `resolved`/`target` only when
  binding succeeded (`TreeWriter.cs:518-522`). PHP: `CallTarget` (`src/Cs/CallTarget.php`), whose
  `symbol()` (`type.name(param1, param2)`) is built to be **string-identical** to a declaration's `symbol`
  field, so a call can be joined to its declaration by string equality alone
  (`Codebase::declarationOf`, `src/Cs/Codebase.php:209-217`).
- `symbol` = a member declaration's own fully-qualified signature, same `Declared` format as `target`
  (`TreeWriter.cs:34-37,538-546,171` — the exact same `SymbolDisplayFormat` is reused for a doc comment's
  resolved `cref` symbol, so a `cref` can also be joined to a declaration the same way).
- **Call → declaration matching is pure string equality on `symbol()`**, no separate ID/index is emitted by
  the bridge: `Codebase::declarationOf` indexes every `MethodDeclaration`'s `symbol` once, then looks up
  `$call->target->symbol()` (`src/Cs/Codebase.php:209-217`); `Codebase::callersOf` does the mirror lookup
  (`src/Cs/Codebase.php:278-295`). A Go engine reproducing this needs the same guarantee: `target.type` +
  `target.name` + `target.parameters` formatted exactly like `symbol` on the callee.
- `inherited` marks a member that **overrides a base member or implements an interface member**
  (`ImplementsInterfaceMember` walks `type.AllInterfaces` and `FindImplementationForInterfaceMember`,
  `TreeWriter.cs:549-552` — this is whole-hierarchy, not syntactic "has an `override` keyword"). Consumed
  for "is this member allowed to have a fixed contract shape" (`isOverride`, `reachesOwnSignature`,
  `mayFillAContract`).
- Class hierarchy / "may fill a contract": the schema does **not** carry a resolved base-type list — only
  the syntactic presence of a `BaseList` node (`mayFillAContract`, `src/Cs/Node.php:1704-1712`) plus the
  `inherited` flag on any member. No detector today asks "what does X inherit from" by symbol; they only
  ask "does X declare or implement something" (syntactic) or "does this member satisfy a contract"
  (`inherited`, semantic).
- Namespace graph (`src/Cs/NamespaceGraph.php`) is built **entirely from already-emitted per-node facts**:
  it walks every module's tree once, and for each node with a resolved `type` or `target`, maps every named
  type (`ResolvedType::namedTypes()`, includes `inner`) back to the namespace of the type declaration that
  owns that symbol (via `whereType()`'s `symbol` field, `NamespaceGraph::__construct`,
  `src/Cs/NamespaceGraph.php:22-30`). It needs no additional bridge fact — it is pure aggregation over
  `type`/`target`/`symbol`, and could equally be computed inside a Go engine.

---

## 7. Whole-program facts

These require Roslyn's cross-file/cross-reference compilation and **cannot** be produced by parsing one
file in isolation — a Go engine has no way to derive them itself, since it never runs a C# compiler:

| Fact | Where computed | Why it is whole-program |
|---|---|---|
| Every `type`/`target`/`symbol`/`constant`/`inherited`/`forgivesNull` | `TreeWriter.WriteFacts`, driven by `SemanticModel` (`Project.Model`, needs the whole compilation with all references) | Name binding needs every referenced assembly/project resolved together |
| `resolution.calls`/`resolution.resolved` | `TreeWriter.cs:16-21,67-73` | A running tally across every file of the run |
| `blind` on a `cref` | `IsBlind`, `TreeWriter.cs:81-92` — `blindGlobally` scans **every tree in the project** for a `global using` that resolves to nothing (`TreeWriter.cs:83`); per-file `blind` adds CS0246/CS0234 diagnostics on that file's own model | A cref's "did the compiler just not have the reference" verdict depends on evidence gathered from files other than the one the cref lives in |
| `test` (per file) | `Project.IsTest`/`ProjectFile.IsTestProject` (`Project.cs:18`, `ProjectFile.cs:54-58`), driven by the whole solution's `.csproj` graph (`Workspace.cs:48`) | Classifying a file requires knowing which `.csproj` owns it and reading that project's MSBuild properties, not anything in the file itself |
| `errors` (per file) | `tree.GetDiagnostics()` (`TreeWriter.cs:55`) | Syntax diagnostics still require running the full Roslyn parser/compiler pipeline the bridge owns; Go never parses C# itself |
| References used for resolution (framework packs, `obj/project.assets.json`, NuGet cache) | `bridge/roslyn/References.cs`, `Workspace.cs` | Never a build — but still a whole-solution discovery step that precedes every one of the facts above |

These are **derivable downstream from already-emitted per-node facts**, and do not need their own
first-class schema representation (the PHP engine already computes them this way — see the file:line
above each):

- **`NamespaceGraph`** (`src/Cs/NamespaceGraph.php`) — pure aggregation over `type`/`target`/`symbol`
  strings already on the tree.
- **`StateFlow`** (`src/Cs/StateFlow.php`) — pure walk over one type's own children (`kind`, `children`,
  `name`, `type`), no additional bridge fact. Caveat: a `partial` class's other parts may live in another
  file/tree; today's implementation only sees the parts in the same file's `Node`, so cross-file `partial`
  merging (if ever needed) would be a genuine gap, not something already solved.
- **`resolution.calls`/`resolution.resolved`** themselves — recomputable by counting nodes of kind
  `InvocationExpression`/`ObjectCreationExpression`/`ImplicitObjectCreationExpression` and how many carry
  `target`.

---

## 8. Must-carry list

Flat, deduplicated, each tagged by where the fact originates.

**[syntax]** — present without any compiler help, from the parse tree alone:
- `kind` (Roslyn `SyntaxKind` name, verbatim)
- `role` (`statement` / `expression` / `member` / `type` / `other`)
- `start`, `end` (UTF-8 byte offsets, `[start, end)`, trivia excluded)
- `path` (per file)
- `children` (ordered)
- `name` (declarations, identifiers, accessor-owning-member fallback)
- `text` (literal/interpolated-text value, as written/unescaped)
- `operator` (binary/assignment/prefix/postfix operator token text)
- `modifiers` (members, local functions, parameters — ordered list, e.g. `public`, `static`, `override`, `const`, `readonly`, `async`, `partial`, `out`)
- `step` (an expression sitting in a `for`'s incrementor list)
- comments as a separate per-file array: `kind` (`line`/`block`/`doc`), `text` (full trivia incl. markers), `start`, `end`, `code` (heuristic: "reads as a statement, not prose")
- a doc comment's `crefs[].text` (as written)
- presence of a `BaseList` node (a type names a base/interfaces syntactically)

**[semantic-local]** — needs the compiler's semantic model, but only that one node/file's binding (still
whole-*compilation*-under-the-hood, but expressible without cross-file evidence being surfaced elsewhere):
- `type` on a typed expression, a type-only-context type node, a `Parameter`'s declared type, and a `CatchDeclaration`'s caught exception type — always fully-qualified (`global::…`), with `?` for nullable-reference, never a keyword alias
- `nullable` (annotation) accompanying `type`
- `value` (is-value-type) accompanying `type`
- `inner` (named types nested inside a generic/array, flattened, de-duplicated) accompanying `type`
- `constant` (compiler-folded value: literal, enum member, `const`, arithmetic on them, or `typeof` of a concrete type)
- `forgivesNull` (a `!` whose operand's *declaration* is nullable)
- `target` = `{type, name, parameters}` on a resolved invocation/object-creation, formatted to be string-joinable to a declaration's `symbol`
- `symbol` on a member declaration (fully-qualified, with containing type and parameter types)
- `inherited` on a member declaration (overrides a base member OR implements an interface member — computed against the whole interface/base hierarchy, not just an `override` keyword)
- a `cref`'s `symbol` (resolved declaration), `owner` (longest resolving qualifier) and `ownedHere` (is that owner declared in this compilation)
- `errors` per file (compiler diagnostics count — requires running the compiler even though the diagnostics themselves are syntax-only)

**[semantic-whole-program]** — needs evidence from other files/projects, not derivable from this file's own
tree even with a semantic model of only this file:
- `test` per file (which `.csproj` owns the file, and that project's `IsTestProject` MSBuild property)
- a `cref`'s `blind` flag (whether *any* file in the project has an unresolved `global using`, or *this*
  file has CS0246/CS0234 — evidence gathered project-wide)
- the set of references actually used to resolve names (framework packs / `project.assets.json` / NuGet
  cache) — not emitted as data today, but the precondition for every semantic fact above being trustworthy;
  a schema/engine redesign should record *that* a whole-project compile happened and with what reference
  set, so "absent = compiler could not resolve" (the bridge's own invariant, `CONTRACT.md:43`) stays
  auditable
- `resolution.calls` / `resolution.resolved` (aggregate across the whole run) — optional to carry since
  it is recomputable, but note it exists today as a first-class emitted fact
