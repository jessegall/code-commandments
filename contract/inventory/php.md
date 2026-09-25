# PHP Backend Inventory

Scope covered: `src/Ast/**` (Codebase, Query, AstNode ~5987 lines, NodeMatch, TypeName, CodebaseIndex, ValueFlow, Support/{TypeResolver,ChainResolver,ReceiverResolver,Calls,Docblock,DocType,Enums,StructuralHash,ScalarRendering,NamespaceGraph,DataClassShape,...}, Laravel/{LaravelNode,RouteActions,RouteNames,ContainerBindings,BoundaryOperations,ResponseSurface,PageObject}, Spatie/{SpatieDataNode,DataConstructions,TransformerOutput,HydrationSlot,FactoryRef}, Concurrent/ConcurrentNode, PhpTypes/OptionNode), `src/SyntaxNode.php`, `src/Span.php`, `src/ClassField.php`, and a representative sample of `src/Detectors/Backend/**` (~20 detectors across control-flow, exceptions, enums, type-switch, Laravel, Spatie categories). The engine is built on **nikic/php-parser**; every `Codebase::scan()`/`fromString()` parses with `ParserFactory::createForNewestSupportedVersion()` then runs `NodeTraverser(new NameResolver, new ParentConnectingVisitor)` (`src/Ast/Codebase.php:1299-1316`) — so every node in the tree the engine ever reads already has resolved names and a `parent` attribute wired on.

---

## 1. Node kinds

| php-parser class | role | fields/children read | example consumer |
|---|---|---|---|
| `Stmt\Class_` | statement (type decl) | `extends`, `implements`, `stmts`, `namespacedName`, `attrGroups`, `isFinal()`, `isAbstract()`, `getMethods()`, `getProperties()`, `getTraitUses()`, `getMethod()` | `Ast/Codebase.php:1220` (parentMap), `AstNode.php:1435` (isNonFinalClass) |
| `Stmt\Interface_` | statement (type decl) | `extends`, `namespacedName` | `Ast/Codebase.php:459,1258` |
| `Stmt\Enum_` | statement (type decl) | `stmts` (→ `EnumCase`), `implements`, `namespacedName` | `Ast/Codebase.php:1107` (enumNames), `Ast/Support/Enums.php:40` |
| `Stmt\EnumCase` | member | `expr` (backing literal) | `Ast/Support/Enums.php:52-61` |
| `Stmt\ClassLike` (base) | statement | `namespacedName`, `getMethod()`, `getTraitUses()` | `Ast/Codebase.php:919` (declarationMap, any class-like) |
| `Stmt\ClassMethod` | member (function-like) | `name`, `params`, `returnType`, `stmts`, `isStatic()`, `isFinal()`, `isPrivate()/isProtected()`, `flags` | `Ast/Support/TypeResolver.php:489-499` |
| `Stmt\Function_` | statement (function-like) | `name`, `params`, `returnType` | `Ast/PhpTypes/OptionNode.php:54` |
| `Stmt\Property` | member | `type`, `props` (→ `PropertyItem`), `attrGroups`, `isPublic()`, `isReadonly()`, `hooks` | `Ast/AstNode.php:2233-2244` |
| `Stmt\ClassConst` | member | (visibility flags) | `Ast/Support/NullObjectDefault.php:150` |
| `Stmt\TraitUse` | member | `traits` (list of `Name`) | `Ast/Codebase.php:1149-1157,1199-1204` |
| `Stmt\Return_` | statement | `expr` | `AstNode.php:521-524,671-687` |
| `Stmt\If_` / `ElseIf_` / `Else_` | statement (branch) | `cond`, `stmts`, `elseifs`, `else` | `AstNode.php:895-908,4020-4070` |
| `Stmt\Switch_` | statement (branch) | `cond` (→ `Case_` arms) | `AstNode.php:4878-4886` |
| `Stmt\Case_` | branch arm | — | `Ast/Codebase.php:580-589` (class member enum is separate `EnumCase`; this is switch-case) |
| `Stmt\For_` | statement (loop) | `loop` (step exprs) | `AstNode.php:775-802` |
| `Stmt\Foreach_` | statement (loop) | `expr`, `valueVar`, `keyVar` | `AstNode.php:189-194`, `ValueFlow.php:299-306` |
| `Stmt\While_` / `Do_` | statement (loop) | `cond`, `stmts` | `AstNode.php:895-908` |
| `Stmt\TryCatch` / `Catch_` | statement | `types` (caught), `stmts` | `AstNode.php:3381-3411` |
| `Stmt\Break_` / `Continue_` | statement | — | `AstNode.php:4040-4050` (bail-out test) |
| `Stmt\Expression` | statement wrapper | `expr` | `Ast/Laravel/LaravelNode.php:381` |
| `Stmt\Namespace_` | statement | `name` | `Ast/Codebase.php:526`, `Ast/Support/DocType.php:149` |
| `Stmt\Use_` / `GroupUse` / `UseItem` | statement (import) | `uses`, `type`, `prefix`, `alias` | `Ast/Support/DocType.php:110-145` |
| `Expr\MethodCall` / `NullsafeMethodCall` | expression (call) | `var` (receiver), `name`, `args` | pervasive, e.g. `AstNode.php:1506-1511` |
| `Expr\StaticCall` | expression (call) | `class`, `name`, `args` | `AstNode.php:1534-1553` |
| `Expr\FuncCall` | expression (call) | `name`, `args` | `Ast/Support/Calls.php:31-33` |
| `Expr\New_` | expression (construction) | `class`, `args` | `Ast/Codebase.php:386-394` |
| `Param` (constructor arg) | member/other | `var`, `type`, `default`, `flags`, `variadic`, `attrGroups`, `hooks` | `Ast/AstNode.php:2220-2231`, `Ast/Support/TypeResolver.php:471-479` |
| `PropertyItem` | member | `name`, `default` | `AstNode.php:1385-1392` |
| `PropertyHook` | member | `name` (`get`/`set`), body | `Ast/Codebase.php:489-493`, `AstNode.php:1103-1107` |
| `Expr\Assign` / `AssignOp` (+ `Coalesce`,`Minus`,`Plus`) | expression | `var`, `expr` | `AstNode.php:340-355,530-536` |
| `Expr\PostInc/PreInc/PostDec/PreDec` | expression | `var` | `AstNode.php:344,794-802` |
| `Expr\BinaryOp\{BooleanAnd,BooleanOr,LogicalAnd,LogicalOr}` | expression | `left`,`right` | `AstNode.php:818-843` |
| `Expr\BinaryOp\Coalesce` | expression | `left`,`right` | `AstNode.php:180-224,847-858` |
| `Expr\BinaryOp\{Identical,NotIdentical,Equal,NotEqual}` | expression | `left`,`right` | `AstNode.php:1109-1124` |
| `Expr\Ternary` | expression | `cond`,`if`,`else` | `AstNode.php:201-224,3801-3821` |
| `Expr\Match_` / `MatchArm` | expression | `cond`, `arms` (`conds`,`body`) | `AstNode.php:135-199,671-676` |
| `Expr\Instanceof_` | expression | `expr`, `class` | `AstNode.php:571-740` (type switches) |
| `Expr\Isset_` / `Empty_` | expression | `vars`/`expr` | `AstNode.php:391-403,1179-1188` |
| `Expr\Throw_` | expression | `expr` | `AstNode.php:172-175` |
| `Expr\PropertyFetch` / `NullsafePropertyFetch` | expression (member) | `var`, `name` | pervasive |
| `Expr\StaticPropertyFetch` | expression (member) | `class`,`name` | `AstNode.php:332-416` (static state) |
| `Expr\ArrayDimFetch` | expression | `var`,`dim` | `ValueFlow.php:295-309` |
| `Expr\Array_` / `ArrayItem` | expression/other | `items`, `key`,`value`,`unpack` | `AstNode.php:1085-1096`, `ValueFlow.php:267-346` |
| `Expr\Cast` (+ `Cast\String_`) | expression | `expr` | `AstNode.php:166,1477` |
| `Expr\ClassConstFetch` | expression | `class`,`name` | `AstNode.php:1602,1627-1636` (class literals) |
| `Expr\ConstFetch` | expression | `name` | `AstNode.php:1037,1070-1077` (`true/false/null`, global consts) |
| `Expr\Variable` | expression | `name` | pervasive; `AstNode::variableNameOf` |
| `Expr\ArrowFunction` / `Closure` | expression (function-like) | `params`,`returnType`,`expr`/`stmts`,`uses` | `NodeMatch.php:53-68`, `Ast/Support/TypeResolver.php:407-433` |
| `Expr\Yield_` | expression | — | imported, generator detection |
| `Scalar\String_` | literal | `value` | pervasive |
| `Scalar\Int_` / `Float_` | literal | `value` | `AstNode.php:1030-1040` |
| `Scalar\InterpolatedString`/`Encapsed`/`EncapsedStringPart` | literal | parts | `AstNode.php:3500` (thrown-with-message) |
| `Scalar\MagicConst` | literal | — | imported (e.g. `__CLASS__`) |
| `Identifier` | name (builtin/member) | `toString()` | pervasive |
| `Name` (+ `FullyQualified`) | name (class ref) | `toString()`, `getLast()`, `isSpecialClassName()` | `Ast/Codebase.php:517-530` (whereClassReference) |
| `NullableType` / `UnionType` / `IntersectionType` | type | `type`/`types` | `Ast/TypeName.php` entire file |
| `Attribute` / `AttributeGroup` | other (metadata) | `name`, `args` | `Ast/Codebase.php:544-557`, `Ast/ClassField.php:53-64` |
| `Arg` | other | `value`, `name`, `unpack` | pervasive (`arguments()`) |
| `Comment` / `Comment\Doc` | other (trivia) | `getText()`, `getStartLine()/getEndLine()` | `Ast/Support/Docblock.php`, `AstNode.php:2552-2601` |

## 2. Per-node scalar facts

- **Class/member modifiers** (via `Modifiers::` bitmask on `flags`, and php-parser convenience methods): `PUBLIC`/`PRIVATE`/`PROTECTED` (`AstNode.php:2152,2226,2284`, `Ast/Support/ClassLayoutOrder.php:129-130`), `READONLY` (`AstNode.php:2184`), `STATIC` (`Ast/Support/ClassLayoutOrder.php:114`, `AstNode.php:2510-2513`), `isFinal()`/`isAbstract()` (`AstNode.php:1435`), `isStatic()` on `ClassMethod` (`AstNode.php:551`), `isPrivate()/isProtected()` (`AstNode.php:4975-4976`), `isPublic()`/`isReadonly()` on `Property` (`AstNode.php:2158,2190,2239,2295`).
- **Params**: `flags !== 0` = promoted (`AstNode.php:498-501,2220`), `variadic` (`AstNode.php:512,3009,4915`, `ValueFlow.php` promoted-param handling, `Ast/Support/TypeResolver.php:509-517` methodIsVariadic), `default` presence/value, `byRef` is imported/available via `Param` node though no direct read was found beyond param shape (`ParamList`/`ParamTarget`).
- **Nullability**: computed from type shape, not a flag — `TypeName::isNullable/isNullableUnion/isNullableArray` (nullable `?T`, `T|null` union) (`Ast/TypeName.php:102-141`); `TypeResolver::paramAcceptsNull` treats untyped, `?T`, `mixed`, `T|null`, or a `null` default all as "may be null" (`Ast/Support/TypeResolver.php:520-527`).
- **Operators**: `Coalesce` (`??`), `BooleanAnd/Or`, `LogicalAnd/Or`, `Identical/NotIdentical/Equal/NotEqual`, compound assign (`+=`/`-=`/`??=`), inc/dec (`++`/`--`) — each tested by `instanceof` (`AstNode.php:180-224,340-355,794-802,818-843`).
- **Literal values**: string value (`String_->value`), int/float value, `''`/`0`/`0.0`/`false`/`[]` recognised as "fake absence" literals (`AstNode.php:1030-1051`), enum backing literal read off `EnumCase->expr` (`Ast/Support/Enums.php:113-119`), PHP_EOL / newline-only string detection (`AstNode.php:1068-1077`).
- **Names**: short name via `Identifier::toString()`/`Name::toString()`; FQCN is whatever `NameResolver` already resolved into the node (so a "resolved name" is not a separate attribute the engine reads — resolution happens once at parse time and the resolved `Name` node IS what every consumer reads) (`Ast/Codebase.php:1303`, `Ast/AstNode.php:1534-1543` normalizes `self`/`static` to the enclosing class name).
- **`self`/`static`/`parent`** are read specially and resolved to the enclosing class FQCN by hand where semantics require it (`AstNode.php:1542,1630,2504`, `Ast/Support/TypeResolver.php:263,287,544-547`).
- **Attribute args**: read as `Foo::class` literal or plain string (`Ast/Support/TypeResolver.php:385-391`, `Ast/AstNode.php:1602-1636`).

## 3. Spans & positions

- Offsets are **byte offsets**, read via `getStartFilePos()`/`getEndFilePos()` — both **inclusive** in php-parser. The engine's own `Span` type is `[start, end)` with an **exclusive** end, so `NodeMatch::span()` does `getEndFilePos() + 1` (`Ast/NodeMatch.php:284-292`, doc comment states this explicitly).
- Lines: `getStartLine()`/`getEndLine()`, 1-based (`Ast/NodeMatch.php:242-245`, `AstNode.php:5507` computes a node's line count as `endLine - startLine + 1`, `AstNode.php:599,607` compares `getStartFilePos()` between two nodes to determine source order — i.e. ordering is byte-offset based, not line based).
- File path travels alongside every match via `ParsedFile::$path` (`Ast/ParsedFile.php:16-25`); `NodeMatch::file()`/`location()` render `path:line` (`Ast/NodeMatch.php:266-277`).
- `Span::slice()` cuts source text using an **inclusive** end (`endInclusive + 1 - start`) — i.e. two different end-inclusivity conventions coexist in the codebase (`Span` type itself is exclusive-end; its `slice()` static helper takes an inclusive end) — a schema should pick ONE convention and state it unambiguously (`src/Span.php:44-48`).
- Anonymous classes have no name; they're disambiguated for keying purposes by `path + getStartFilePos() + spl_object_id()` (`Ast/Codebase.php:1231-1232`) — i.e. **identity of an unnamed declaration needs its span**, not just its name.
- `getAttribute('parent')` is read constantly to walk upward (`AstNode::ancestorsOf`, `AstNode.php:917-926`) — the tree the schema emits must carry parent-navigability (or the Go engine must build it, but every "what encloses this" / "what branch decided this" fact in section 6 depends on it).

## 4. Comments & docblocks

- `getDocComment()` returns the **last** `/** */` block attached to a node; `getComments()` returns **every** comment (line + doc) attached, in source order — the engine explicitly notes PHP's "last docblock wins" pitfall and reads `getComments()` instead when it needs every stacked docblock (`AstNode.php:2546-2563`, `docblocks()`).
- Docblock **shape** facts (delimiter placement, per-line `*`, inline vs. block) are read purely from `getText()` string content, not from any parser structure — see `Ast/Support/Docblock.php` (`isInline`, `canonical`, `merge`, `foldable`).
- Docblock **tag content** is regex-parsed from `getText()`: `@param`/`@var` type + `$name` (`Ast/Support/DocType.php:163-178`), `@see`/`@link` cross-references (`AstNode.php:2257-2268`), which parameter names are documented (`Ast/Support/Docblock.php:112-123`).
- Line comments (`//`, `#`) are distinguished from doc comments by `instanceof Comment\Doc` and text prefix (`AstNode.php:2393-2406`).
- Multi-paragraph docblocks are detected by blank-line runs inside the text (`AstNode.php:3508+`).
- Stacked-docblock foldability additionally needs each block's own **span** (`getStartLine()`/`getEndLine()`) to tell "adjacent" from "orphaned by a blank line" (`AstNode.php:2584-2601`).
- "Commented-out code" is detected by re-parsing the comment's stripped text as PHP and checking for zero syntax errors (`Ast/Support/CommentedCode.php`) — i.e. comment text must be re-tokenizable, not just stored as an opaque string.
- Docblock **placement** matters: attached to whichever declaration node php-parser attaches it to (a property, a promoted param, a method, a class, an arrow function) — read per-node via that node's own `getDocComment()`.

## 5. Types

- **Declared types** live on `Param->type`, `Property->type`, `FunctionLike->returnType`(`getReturnType()`), each possibly `NullableType`, `UnionType`, `IntersectionType`, `Name`, or `Identifier` (builtin). All read and normalized by `TypeName` (`Ast/TypeName.php`): `class()` (single class FQCN, seeing through `?`/union), `simpleName()` (as-written, keeps builtins), `nullableClass()`, `isNullable/isNullableArray`, `render()` (canonical comparable string, union members sorted), `unionIncludes()` (marker-type-in-union, e.g. Spatie `Optional`), `promisesScalar()`.
- **`overlaps()`** answers whether two rendered type strings could share a value (widening `array`⊇`iterable`, `static/self/$this`→`self`) — a semantic-local fact for "do two functions return compatible types" (`Ast/TypeName.php:149-186`).
- **Resolved/inferred types** (whole-program, memoised per `Codebase`): `TypeResolver` (`Ast/Support/TypeResolver.php`) indexes, per class, every field's declared type, every method's return type + per-position param type/nullability/variadic-ness, and walks locals (`typeOf`) through assignment origin, params, captured closure vars, and typed-collection `foreach`. `declaringClassOf`/`declaringClassOfMethod` resolve a field/method to the ancestor that actually declares it (inheritance-aware). `collectionElementOf` reads the **element type of a typed collection** two ways: `#[DataCollectionOf(X::class)]` attribute (Spatie-specific) or `@var list<X>`/`X[]`/`Collection<int,X>` docblock generics resolved through the file's imports (`DocType`) — PHP's own type system stops at the container type, so this is pure semantic inference the schema must be able to reconstruct or the bridge must precompute it.
- **`ChainResolver`** is a second, independent whole-tree type index (property types + zero-arg method return types keyed by class) used to resolve fluent/property chains one hop at a time (`Ast/Support/ChainResolver.php`) — duplicate machinery to `TypeResolver`, both whole-program.
- **`ReceiverResolver`** resolves a call's receiver type locally (no whole-program index): `$this`→enclosing class, a typed param, or a typed `$this->prop` (`Ast/Support/ReceiverResolver.php`).
- **`Codebase::isValueType()`** — a recursive, depth- and cycle-bounded whole-program classification of "is this type data (value) or a service": scalars/`array`/`iterable` are values; an enum or an `Option` type is a value; a class is a value only if EVERY one of its own fields (via `declarationMatch()->fields()`) is itself a value, recursively (`Ast/Codebase.php:981-1043`).
- **Field/property presence-in-hierarchy**: `Codebase::inheritsMutableProperty()` checks whether ANY ancestor declares a property without `readonly` (PHP forbids redeclaring a mutable inherited property as readonly) (`Ast/Codebase.php:718-724`).

## 6. Symbols & call targets

- **Method-send detection**: `AstNode::isMethodSend`/`isNamedSend` unify `MethodCall`/`NullsafeMethodCall` (and static sends) so every predicate treats `->` and `?->` alike.
- **Static call resolution**: `staticCallClass()` resolves `self`/`static` to the enclosing class FQCN so callers never special-case them (`AstNode.php:1534-1543`).
- **`new` resolution**: `newClassName()`/`newClassNode()` (`AstNode.php:1516-1527`); `whereNewExtending()` filters by transitive `extends`.
- **Class hierarchy** (whole-program, built once per `Codebase`, all keyed by FQCN read off `namespacedName`): `parentMap()` (single extends-parent per class, `Ast/Codebase.php:1210-1239`), `interfaceMap()` (direct `implements`/interface-`extends`, `1248-1276`), `traitUserMap()`/`traitMethodsOf()` (who `use`s a trait and what methods it contributes, since php-parser's `getMethods()` doesn't see trait-provided methods, `1139-1208`), `declarationMap()` (every class/enum/interface/trait keyed by FQCN + its file, `901-930`), `classNodeMap()`/`enumNames()`.
- **Derived hierarchy queries**: `extends()`/`ancestorsOf()` (walk `parentMap`, cycle-safe), `implements()` (BFS through both the `extends` chain and interface-extends-interface edges), `isA()` (self, extends, or implements), `isEnum()`/`isInterface()`, `hasSubclass()` (is this a non-leaf base — relevant to "can this be `final`").
- **Method-declaration resolution across inheritance**: `overridesMethod()` (reflection for autoloadable/vendor ancestors, else walks the parsed graph including interfaces, `Ast/Codebase.php:784-848`) — needed so a naming rule doesn't flag an inherited/contractual name; `methodReturnsNullable()` resolves to the *declaring* class before reading `returnType`.
- **Call graph** (`Ast/CodebaseIndex.php`): one scan of every `->method()` and `Class::method()` bucketed by method **name**; `callersOf($fqcn, $method)` filters that bucket by receiver type (static call's named class, or `ReceiverResolver::typeOf`) matching `$fqcn` or a subclass of it — both spellings (instance and static) count as call sites of the same declaration.
- **`trace()`** (`Ast/NodeMatch.php:375-399`) walks every occurrence of a **named local variable** within its enclosing function in source order, classifying each via `interactionKind()` (`Ast/AstNode.php:1322-1339`, enum `InteractionKind`: Assigned/NullChecked/Coalesced/Nullsafe/Returned/MethodCall/PropertyWrite/PropertyFetch/Argument/Read).
- **Value-flow / provenance graph** (`Ast/ValueFlow.php`, whole-program, memoised): follows a class field's value forward through assignment, argument-passing (into nullable vs. non-nullable params, by name or position via `ParamTarget`), `return` (via the call graph's `callersOf`), field-writes (`$this->g = …`), array insertion/element-read/`foreach`/spread, and Spatie's `X::from([...])` hydration array — terminating at a **guard** (null-check/type-check/owner-predicate) or an **assume** (dereference, or landing on a non-nullable param) with zero contradiction required to be safe (`readVerdict`, `FlowVerdict`). Any read whose receiver type can't be resolved makes the whole field **untraceable** rather than silently dropped (`fieldReads()` comment at `ValueFlow.php:649-658`). Produces `explain()`/`chainPath()` — human-auditable evidence chains of `kind@file` steps.
- **Declaration lookup by name**: `classNamed()` (`Class_` only, `AstNode`-wrapped), `declarationMatch()` (any class-like, `NodeMatch`-wrapped, with file) — the two seams every symbol-resolution fact goes through.
- **Route/container/event wiring** (Laravel-specific but structurally "symbol resolution"): `RouteActions`/`RouteNames`/`ContainerBindings` (not fully read line-by-line but confirmed via `LaravelNode` to build whole-program indices of registered route actions, route names, and container bindings) — same "index once, query many" pattern as `CodebaseIndex`.

## 7. Whole-program facts

Facts that require seeing the **entire scanned tree** (a bridge from a single-file syntax tree cannot compute these; either the schema must let a whole-program pass run over many trees, or the PHP bridge must precompute and attach them):

- Class/interface/trait hierarchy (`parentMap`, `interfaceMap`, `traitUserMap`) — every `extends`/`implements`/`use` edge across every file.
- The call graph (`CodebaseIndex::callersOf`) — every call site of a method name, bucketed and matched by resolved receiver type.
- The value-flow/provenance graph (`ValueFlow`) — needs the call graph plus type resolution plus every field-read in the tree.
- `TypeResolver`'s per-class field/return/param type index (built by one pass over every class in every file) — local `typeOf()` resolution depends on this global index.
- `Codebase::isValueType()` — recursively inspects a class's OWN fields' types, which requires the whole-program field-type index.
- `NamespaceGraph` — dependency edges between namespaces folded up from every class reference in the tree; mutual-pair/cycle detection, topological ordering.
- `Enums` index — every backed enum's case values, used to catch a *different* file's string literals silently mirroring an enum defined elsewhere.
- `ScalarRendering` — a class's constant `__toString()` value, needed by callers elsewhere to know a class always renders as e.g. `''`.
- `DataClassShape`/`PageObject`/`ResponseSurface` (Spatie/Laravel) — "does this Data class transitively compose ≥2 nested Data classes AND get returned from some controller action" — crosses class boundaries and route registrations.
- `overridesMethod`/`ancestorDeclares` — falls back to PHP reflection for **vendor/autoloadable** classes outside the scanned tree, i.e. some whole-program facts also depend on the runtime environment (installed Composer packages), not just the parsed files.

Facts that are purely **per-file / local** and could be computed from a single syntax tree without cross-file knowledge: everything in sections 1-4, `ReceiverResolver` (deliberately local/conservative), `StructuralHash`, `Docblock`, `DocType` (though `DocType::resolve()` needs the *file's* own imports, not other files), guard/branch/nesting/ternary/loop-shape predicates, `ClassLayoutOrder`.

## 8. Package-specific extras

- **Laravel** (`Ast/Laravel/LaravelNode.php` — one class holding every framework FQCN as a `const string`/`const array`, reused by other Laravel support classes): facade namespace prefix (`Illuminate\Support\Facades\`), `ServiceProvider`, Eloquent `Model`, HTTP `Request`/`FormRequest`, Laravel MCP `Request`/`Tool`, `Inertia\Inertia` + `inertia()` helper, `Routing\Controller`, `Route` facade + verb list (`get/post/put/patch/delete/options/match/any`), router types for route-group closures, container binding methods (`bind/bindIf/singleton/...`), Eloquent relation methods (`hasOne/belongsTo/...`) as *symmetric association* markers, Eloquent binding attributes (`#[ObservedBy]`, `#[ScopedBy]`, `#[CollectedBy]`, `#[UsePolicy]`), `ShouldQueue` + fixed-signature queue hooks (`failed/middleware/retryUntil/...`), `Event` facade + dispatch method names, `Console\Command`, route-name lookup call names (`route/to_route/signedRoute/...`) and URL-generator facades/helpers, `Auth\Guard`/`UserProvider` contracts, Eloquent cast contracts. Behaviours read: is-facade-call (by resolved static-call class prefix), route-name string-literal argument, event-class argument (`X::class` literal), bound container abstract, route-action detection (delegates to a separate `RouteActions` whole-program index), thin-controller-delegation detection (single-statement method forwarding to another *registered* route action), mass `->update([...])` on a non-`$this` Eloquent-typed receiver.
- **Spatie** (`Ast/Spatie/SpatieDataNode.php`, 1977 lines — by far the densest decorator): `Spatie\LaravelData\Data` base, `DataPipe`/`Cast` contracts, container-injection attributes (`#[FromContainer]`, `#[FromContainerProperty]`, `#[FromRouteParameter]`, `#[FromAuthenticatedUser]`, …), `#[Hidden]`, native-cast type list, known TypeScript-transformer classes, `Spatie\LaravelData\Optional`, `#[TypeScript]`/`#[TypeScriptType]`/`#[LiteralTypeScriptType]`, `Spatie\LaravelData\DataCollection`, `#[DataCollectionOf(X::class)]` element-type attribute, `#[WithCast]`/`#[WithCastAndTransformer]`/`#[WithCastable]`, `#[Computed]` (marks a property-hook getter as NOT a hydration input), `#[Eager]`, `#[MapInputName]`/`#[MapName]` (input-key remapping). Reads attribute args as either a class-const (`X::class`) or string literal. Also resolves "hydration slots" (`HydrationSlot`: which `Data::from([...])` array key a value lands on, its declared/element type, whether it's a collection, whether the destination already has a cast) and "mapped factories" (`FactoryRef`: what `array_map()` callback builds each element, and whether that callback closes over outside state).
- **Concurrent** (`jessegall/concurrent`): a single FQCN constant (`JesseGall\Concurrent\Concurrent`) and one predicate — does a class transitively `extend` it (whole-program `extends` walk).
- **PhpTypes** (`jessegall/php-types`): `Option` type matched by **short name only** (`ClassName::short($class) === 'Option'`) — deliberately name-based, not FQCN-based, since a project's own `use` brings it in under that name. Reads: declared-type is a nullable `Option` (`?Option`/`Option|null`), and `->unwrapOr(null)` call shape (collapsing an Option to nullable).

## 9. Must-carry list

**[syntax]** (pure tree shape/tokens, no semantic pass needed)
- Every node's concrete kind, distinguishing at minimum: class/interface/trait/enum declarations (+ enum case + its backing literal), function/method/closure/arrow-function declarations (+ params: name, type, default, promoted-flag, visibility, variadic, byRef, hooks), properties (+ hooks) and promoted-param fields (type, default, visibility, readonly, static), constants, trait-use, assignment + all compound-assign/inc-dec variants, all binary comparison/logical/coalesce operators, ternary (full + short), match + arms (+ conditions list, including `null` conds marking `default`), switch + case, if/elseif/else, for/foreach/while/do (+ loop-step exprs, valueVar/keyVar), try/catch (+ caught types list) , throw, instanceof, isset/empty, casts, method/static/function calls + args (positional/named/variadic-unpack), `new`, property/array-dim fetch (+ nullsafe variants), class-const fetch, global const fetch, variable, all scalar literal kinds (string incl. interpolated parts, int, float), attributes + their args, return/break/continue.
- Modifier flags: `final`, `abstract`, `readonly`, `static`, `abstract`, visibility (public/private/protected), variadic, byRef, nullable-sugar (`?T`) vs. explicit union-with-null.
- Type nodes: nullable wrapper, union, intersection, plain name/identifier — as-written, not collapsed.
- Literal values verbatim (string content, numeric value, backing enum-case value).
- Names as resolved at parse time (FQCN for class references; `self`/`static`/`parent` kept distinguishable from a real class name until resolved against enclosing class).
- Byte-offset start/end (pick one inclusive/exclusive convention and document it — the current engine mixes an exclusive-end `Span` type with an inclusive-end `slice()` helper) and 1-based start/end line, plus file path, for every node.
- Every comment (line `//`/`#` and doc `/** */`) with its own text and span, associated to the node it's attached to (PHP's "last docblock wins" pitfall means the schema should expose ALL attached comments per node, not just one).
- Parent-navigability (or an equivalent the Go engine can rebuild) — essentially every non-trivial predicate in AstNode.php climbs `parent()`/`ancestors()`.

**[semantic-local]** (computable from one file's tree + its own imports, no cross-file index)
- Declared type normalized/rendered form (nullable-ness, union member set, single-class-of-union) — `TypeName`'s whole surface.
- Docblock `@var`/`@param`/`@return`/`@see`/`@link` tag content parsed and, for a class reference, resolved through the FILE's own `use`-imports/namespace (not global) — `DocType`.
- "Is this node a null-guard / type-guard / branch-condition / bail-out statement" family — purely structural, but needs full ancestor/sibling context within the enclosing function.
- Structural-equality fingerprint of an expression subtree — exact, "normalized" (blank names/literals for clone-2 detection), and "shape" (blank leaf operands) — `StructuralHash`, built from node class name + every declared sub-node name/value, recursively. The schema must expose child fields in a stable, enumerable way (equivalent to php-parser's `getSubNodeNames()`) for this to be reproducible in Go.
- Local variable/type flow within a single function (assignment origin, closure/arrow-fn capture, typed-`foreach` element inference) — the file-local half of `TypeResolver`.
- Receiver type of a call resolved conservatively from `$this`, a typed param, or a typed `$this->prop` — `ReceiverResolver`.
- Interaction classification of each occurrence of a traced local variable (assigned/null-checked/coalesced/nullsafe/returned/call-receiver/property-read-or-write/argument/plain-read).

**[semantic-whole-program]** (needs every file in the scan, or beyond — e.g. installed vendor packages via reflection)
- Full class/interface/trait hierarchy: extends chain, implements set (with interface-extends-interface transitivity), trait usage and the methods a trait contributes to its users.
- Call graph: every call site of a given method name, resolved to a receiver type, matched against a target class or its subclasses (covers both instance and static call spellings of the same declaration).
- Value-flow/provenance graph over class fields: forward propagation through assignment, call arguments (nullable vs. non-nullable parameter targets), returns (via the call graph), field writes, and array carriage (insertion/element-read/foreach/spread/hydration-array), terminating in a guard-vs-assume verdict, with "unresolvable read ⇒ whole field untraceable" as the conservative default.
- Per-class index of every field's declared type, every method's return type, and every parameter's type/nullability/variadic-ness — resolved to the class that actually DECLARES each member (inheritance-aware), including the collection ELEMENT type read from a docblock generic or a `#[DataCollectionOf]` attribute (a fact PHP's own type system cannot express).
- Recursive "is this type a value vs. a service" classification, walking a class's own field types transitively (depth- and cycle-bounded).
- Namespace dependency graph folded up from every class reference in every file, with association-attribute/call exemptions, mutual-cycle and topological-order derivation.
- Whole-tree enum case-value index (to catch string/int literals elsewhere silently mirroring a closed enum).
- A class's constant `__toString()` render value (single-statement `return '<literal>'` bodies only).
- Fallback resolution through PHP reflection for ancestors/interfaces that live in installed vendor code outside the scanned tree (hierarchy and method-declaration checks silently extend past the parsed file set) — a fact a language-neutral Go engine cannot reproduce without either an equivalent host-language reflection step per bridge, or the bridge pre-resolving and embedding these edges.
- Package-specific whole-program indices: Laravel route-action/route-name/container-binding registries (`RouteActions`/`RouteNames`/`ContainerBindings`), Laravel `PageObject`/`ResponseSurface` (Data class composing ≥2 nested Data AND reachable from a controller/Inertia response), Spatie `DataClassShape` (richness inherited across the class hierarchy).

