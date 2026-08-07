<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast;

use JesseGall\CodeCommandments\Files\FileQuery;
use JesseGall\CodeCommandments\Support\ClassName;
use JesseGall\CodeCommandments\Support\Invokable;
use JesseGall\CodeCommandments\Support\NoOp;
use JesseGall\CodeCommandments\Support\PhpFile;

use JesseGall\CodeCommandments\ExcludedPaths;
use JesseGall\CodeCommandments\WorkingCopy;
use FilesystemIterator;
use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\PropertyHook;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\UnionType;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\ParserFactory;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The entry point to the query engine: parses a set of files (names resolved,
 * parents linked) and opens fluent {@see Query} builders over them. Each
 * `where*()` selects a kind of node; chain filters on the returned Query.
 */
final class Codebase implements \JesseGall\CodeCommandments\Codebase
{
    /**
     * Directories never descended into during a scan — dependency and VCS trees
     * that aren't code under review.
     */
    // Dirs never descended into, anywhere — dependency trees that aren't code under
    // review. (Hidden `.dirs` and symlinks are pruned separately; WHICH source roots
    // to scan is the canon's job, not a denylist's.)
    private const array SKIP_DIRS = ['vendor', 'node_modules'];

    /**
     * @param  list<ParsedFile>  $files
     *
     * @var array<string, string>|null  child FQCN => parent FQCN
     */
    private ?array $parentMap = null;

    /**
     * @var array<string, list<string>>|null  trait FQCN => FQCNs of the class-likes that `use` it
     */
    private ?array $traitUserMap = null;

    /**
     * @var array<string, list<string>>|null  class FQCN => directly-implemented interface FQCNs
     */
    private ?array $interfaceMap = null;

    /**
     * @var array<string, Class_>|null  class FQCN => declaration node
     */
    private ?array $classNodeMap = null;

    /**
     * @var array<string, NodeMatch>|null  ANY class-like (class/enum/interface/trait) FQCN => its match
     */
    private ?array $declarationMap = null;

    /**
     * @var list<string>|null  every enum FQCN in the codebase
     */
    private ?array $enumNames = null;

    private ?CodebaseIndex $index = null;

    private ?ValueFlow $valueFlow = null;

    private ?Support\Projection $projection = null;

    /**
     * @var array<class-string<Node>, list<array{0: Node, 1: ParsedFile}>>|null
     */
    private ?array $nodeBuckets = null;

    /**
     * @var array<class-string<Node>, list<class-string<Node>>>  requested node class => the bucket keys it covers
     */
    private array $bucketsByType = [];

    /**
     * @var array<string, string>|null
     */
    private ?array $sourceByPath = null;

    private function __construct(private readonly array $files) {}

    /**
     * A query match for a node — a plain {@see NodeMatch}, or the $as decorator a typed `where`
     * closure asked for (reflection on the closure picks the class, so a `fn (LaravelNode $n) => …`
     * reads like a built-in — see {@see Query::where}; no registration needed). The single place
     * matches are built, and where the codebase hands itself to the node so a decorator can answer
     * whole-program questions (`extends`, `implements`, receiver types) — the class graph a package
     * predicate needs.
     *
     * @param  class-string<NodeMatch>|null  $as
     */
    public function wrap(Node $node, ParsedFile $file, ?string $as = null): NodeMatch
    {
        $class = $as ?? NodeMatch::class;

        return new $class($node, $file, $this);
    }

    /**
     * The call graph over these files (who calls what, receiver types). Built once.
     */
    public function index(): CodebaseIndex
    {
        return $this->index ??= new CodebaseIndex($this);
    }

    /**
     * The value-flow (provenance) graph over these files — where a field's value travels and how it
     * is consumed. Built once, cached here like {@see index}.
     */
    public function valueFlow(): ValueFlow
    {
        return $this->valueFlow ??= new ValueFlow($this);
    }

    /**
     * The projection reader — is an array literal the wire shape of a type that already exists, or an
     * unborn one? Built once, cached here like {@see valueFlow}.
     */
    public function projection(): Support\Projection
    {
        return $this->projection ??= new Support\Projection($this);
    }

    /**
     * Every [node, file] pair of the given node classes — pulled from an index
     * bucketed by node type and walked ONCE, then cached for the codebase's life.
     * Pass null for every node.
     *
     * This is the engine's anti-quadratic guarantee: a selector visits only the
     * nodes of its own kind (every `new`, every parameter, …), so a query run once
     * per candidate scans a small bucket — not the whole tree on every call.
     *
     * A requested type matches its SUBCLASSES too, so a selector may name the base
     * kind it means (`Name`, which php-parser resolves into `Name\FullyQualified`)
     * without having to enumerate the leaves php-parser happens to use — an omission
     * that would otherwise show up as a selector silently matching nothing. Name
     * disjoint types: a base AND its own subclass in one call yields that subclass twice.
     *
     * @param  list<class-string<Node>>|null  $types
     * @return iterable<array{0: Node, 1: ParsedFile}>
     */
    public function nodes(?array $types = null): iterable
    {
        $buckets = $this->nodeBuckets ??= $this->bucketNodes();

        foreach ($types ?? array_keys($buckets) as $type) {
            foreach ($this->bucketsOf($type) as $bucket) {
                yield from $buckets[$bucket];
            }
        }
    }

    /**
     * The bucket keys a requested node class covers — itself and every subclass of it that the
     * scan actually produced. Memoised per codebase, since a query asks on every run.
     *
     * @param  class-string<Node>  $type
     * @return list<class-string<Node>>
     */
    private function bucketsOf(string $type): array
    {
        return $this->bucketsByType[$type] ??= array_values(array_filter(
            array_keys($this->nodeBuckets ?? []),
            static fn (string $bucket): bool => $bucket === $type || is_subclass_of($bucket, $type),
        ));
    }

    /**
     * Walk every file's AST once and bucket every node by its concrete class — the
     * single shared index all selectors filter.
     *
     * @return array<class-string<Node>, list<array{0: Node, 1: ParsedFile}>>
     */
    private function bucketNodes(): array
    {
        $finder = new NodeFinder;
        $buckets = [];

        foreach ($this->files as $file) {
            foreach ($finder->find($file->ast, static fn () => true) as $node) {
                $buckets[$node::class][] = [$node, $file];
            }
        }

        return $buckets;
    }

    /**
     * Parse every `.php` file under the given path(s). Parsing is the slow part of a
     * run, so an optional `$onProgress(int $done, int $total)` is called per file —
     * the caller (e.g. the judge progress bar) can show real progress instead of a
     * frozen "parsing…". Files are enumerated up front so `$total` is known. Several
     * source roots (the canon) can be scanned at once; a file shared by two roots is
     * parsed once.
     *
     * @param  string|list<string>  $path  one source root, or several
     * @param  Invokable  $onProgress  called `($done, $total)` per file; silent by default
     * @param  WorkingCopy  $overlay  pending edits to read THROUGH (empty = straight off disk)
     * @param  ExcludedPaths  $excluded  subtrees pruned from the walk, never read
     */
    public static function scan(string|array $path, Invokable $onProgress = new NoOp, WorkingCopy $overlay = new WorkingCopy(), ExcludedPaths $excluded = new ExcludedPaths()): self
    {
        $paths = [];

        foreach ((array) $path as $root) {
            foreach (self::phpFilesIn($root, $excluded) as $file) {
                $paths[$file] = true;
            }

            foreach ($overlay->createdUnder($root, '.php') as $file) {
                $paths[$file] = true;
            }
        }

        $paths = array_keys($paths);
        $total = count($paths);
        $files = [];

        foreach ($paths as $index => $file) {
            $code = $overlay->read($file);

            if ($code !== null) {
                $files[] = self::parse($code, $file);
            }

            $onProgress($index + 1, $total);
        }

        return new self($files);
    }

    /**
     * Parse a single in-memory source string (handy for unit tests).
     */
    public static function fromString(string $code, string $path = 'memory.php'): self
    {
        return new self([self::parse($code, $path)]);
    }

    /**
     * `$obj->method(...)` calls named one of $names (any, when empty).
     */
    public function whereMethod(string ...$names): Query
    {
        return new Query($this, static fn (Node $node): bool =>
            ($node instanceof MethodCall || $node instanceof NullsafeMethodCall)
            && $node->name instanceof Identifier
            && ($names === [] || in_array($node->name->toString(), $names, true)),
            [MethodCall::class, NullsafeMethodCall::class]);
    }

    /**
     * `Class::method(...)` calls named one of $names.
     */
    public function whereStaticCall(string ...$names): Query
    {
        return new Query($this, static fn (Node $node): bool =>
            $node instanceof StaticCall
            && $node->name instanceof Identifier
            && ($names === [] || in_array($node->name->toString(), $names, true)),
            [StaticCall::class]);
    }

    /**
     * `function(...)` calls named one of $names.
     */
    public function whereFunction(string ...$names): Query
    {
        return new Query($this, static fn (Node $node): bool =>
            $node instanceof FuncCall
            && $node->name instanceof Name
            && ($names === [] || in_array($node->name->toString(), $names, true)),
            [FuncCall::class]);
    }

    /**
     * `new X(...)`, optionally only of the given fully-qualified class.
     */
    public function whereNew(?string $class = null): Query
    {
        $want = $class === null ? null : ltrim($class, '\\');

        return new Query($this, static fn (Node $node): bool =>
            $node instanceof New_
            && ($want === null || ($node->class instanceof Name && $node->class->toString() === $want)),
            [New_::class]);
    }

    /**
     * `new X(...)` where X extends $parent (directly or transitively) — e.g.
     * every `new <Spatie Data subclass>`. Dynamic `new $var` (no resolvable
     * name) is skipped, since it can't be a known subclass.
     */
    public function whereNewExtending(string $parent): Query
    {
        return $this->whereNew()->where(fn (AstNode $node) => $this->extends($node->newClassName(), $parent));
    }

    /**
     * Parameters type-hinted with the given class (a constructor param means the
     * container injects it — i.e. the class is container-resolved). Honours
     * nullable and union/intersection types.
     */
    public function whereParamType(string $class): Query
    {
        $want = ltrim($class, '\\');

        return new Query($this, static fn (Node $node): bool =>
            $node instanceof Param && self::typeContains($node->type, $want), [Param::class]);
    }

    /**
     * Every assignment (`$x = …`). Refine with predicates on the node (the target, the RHS).
     */
    public function whereAssign(): Query
    {
        return new Query($this, static fn (Node $node): bool => $node instanceof Assign, [Assign::class]);
    }

    /**
     * Every class declaration. Refine with `extends`/predicates on the node.
     */
    public function whereClass(): Query
    {
        return new Query($this, static fn (Node $node): bool => $node instanceof Class_, [Class_::class]);
    }

    /**
     * Every FILE in this codebase, as something a rule can judge by NAME — `whereFile()` opens the
     * same fluent query the frontend's does ({@see \JesseGall\CodeCommandments\Files\FileQuery}),
     * because a path is not a language.
     */
    public function whereFile(): FileQuery
    {
        return new FileQuery(array_map(static fn (ParsedFile $file): string => $file->path, $this->files));
    }

    /**
     * Every string LITERAL. The words a user reads are values, not identifiers, so an identifier
     * sweep never sees them (#441) — pair this with {@see AstNode::argumentOfCall} /
     * {@see AstNode::enclosingAttributeName} to judge only the literals reaching a text position.
     */
    public function whereString(): Query
    {
        return new Query($this, static fn (Node $node): bool => $node instanceof String_, [String_::class]);
    }

    /**
     * Every INTERFACE declaration — the contract side of the codebase, which {@see whereClass}
     * (a `Class_` selector) never matches. Refine with predicates on the node the same way.
     */
    public function whereInterface(): Query
    {
        return new Query($this, static fn (Node $node): bool => $node instanceof Interface_, [Interface_::class]);
    }

    /**
     * Every method declaration. Refine with predicates on the node (return type,
     * name, …).
     */
    public function whereMethodDeclaration(): Query
    {
        return new Query($this, static fn (Node $node): bool => $node instanceof ClassMethod, [ClassMethod::class]);
    }

    /**
     * Every FIELD declaration — a promoted constructor parameter or a declared property. The finding
     * sits on the field; read its name/type/attributes via {@see AstNode::asField}.
     */
    public function whereField(): Query
    {
        return new Query($this, static fn (Node $node): bool =>
            ($node instanceof Param && $node->flags !== 0) || $node instanceof Property,
            [Param::class, Property::class]);
    }

    /**
     * Every property-hook GETTER — `public T $x { get => … }` (or `get { … }`), on a declared
     * property or a promoted constructor parameter. A computed slot; refine with predicates on what
     * its body produces.
     */
    public function whereGetterHook(): Query
    {
        return new Query($this, static fn (Node $node): bool =>
            $node instanceof PropertyHook && $node->name->toString() === 'get', [PropertyHook::class]);
    }

    /**
     * Class declarations extending $parent (directly or transitively) — the
     * declaration-side mirror of {@see whereNewExtending}.
     */
    public function whereClassExtending(string $parent): Query
    {
        return $this->whereClass()->where(fn (AstNode $node) => $this->extends($node->enclosingClassName(), $parent));
    }

    /**
     * Every reference to a class-like NAME anywhere in the program — a `use` import, `extends`,
     * `implements`, a `use` of a trait, a parameter/return/property type, `new X`, `X::method()`,
     * `X::CONST`, `instanceof X`, a `catch (X)`, an attribute. In short: the codebase's dependency
     * EDGES, in one selector. Names arrive fully qualified (the scan resolves them), so read the
     * target with {@see AstNode::referencedClassName} and the referring side with
     * {@see AstNode::namespaceName}.
     *
     * Defined by what a class reference is NOT, so nothing is missed as the language grows: the
     * namespace DECLARATION (that says where code lives, not what it depends on), function and
     * constant names (they fall back to the global scope and are not classes), and the relative
     * `self`/`static`/`parent` (which can never cross a boundary).
     */
    public function whereClassReference(): Query
    {
        return new Query($this, static function (Node $node): bool {
            if (! $node instanceof Name || $node->isSpecialClassName()) {
                return false;
            }

            $parent = $node->getAttribute('parent');

            return ! $parent instanceof Namespace_
                && ! $parent instanceof FuncCall
                && ! $parent instanceof ConstFetch;
        }, [Name::class]);
    }

    /**
     * `#[Attr(...)]` usages, matched by short name or fully-qualified name.
     */
    public function whereAttribute(string $name): Query
    {
        $want = ltrim($name, '\\');

        return new Query($this, static function (Node $node) use ($want): bool {
            if (! $node instanceof Attribute) {
                return false;
            }

            $resolved = $node->name->toString();

            return $resolved === $want || ClassName::short($resolved) === $want;
        }, [Attribute::class]);
    }

    /**
     * Any node carrying a comment that matches the given regex (line or doc
     * comment). The finding sits on the commented declaration.
     */
    public function whereComment(string $pattern): Query
    {
        return new Query($this, static function (Node $node) use ($pattern): bool {
            foreach ($node->getComments() as $comment) {
                if (preg_match($pattern, $comment->getText()) === 1) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Every class-body member that is NOT a method — a constant, a property (hooked or plain), an enum
     * case, a trait `use`. What a class HAS, as opposed to what it does.
     */
    public function whereClassMember(): Query
    {
        return new Query(
            $this,
            static fn (Node $node): bool => $node instanceof Property
                || $node instanceof ClassConst
                || $node instanceof EnumCase
                || $node instanceof TraitUse,
            [Property::class, ClassConst::class, EnumCase::class, TraitUse::class],
        );
    }

    /**
     * Every arrow function — `fn (…) => …`, the one-expression form.
     */
    public function whereArrowFunction(): Query
    {
        return new Query($this, static fn (Node $node): bool => $node instanceof ArrowFunction, [ArrowFunction::class]);
    }

    /**
     * Every node carrying a DOCBLOCK — the `/**` documentation comment, wherever it is attached.
     */
    public function whereDocblock(): Query
    {
        return new Query($this, static fn (Node $node): bool => $node->getDocComment() !== null);
    }

    /**
     * Every node carrying a `//` (or `#`) comment — the inline prose a reader meets before a
     * statement, docblocks excluded. Narrow it with {@see AstNode::lineComments()}.
     */
    public function whereLineComment(): Query
    {
        return new Query($this, static function (Node $node): bool {
            foreach ($node->getComments() as $comment) {
                if (! $comment instanceof Doc) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Open a pattern selecting every node, checked by your own predicate over a
     * fluent {@see AstNode}. Chain more `where`/`reject` to refine.
     *
     * @param  \Closure(AstNode): bool  $check
     */
    public function where(\Closure $check): Query
    {
        return (new Query($this, static fn (Node $node) => true))->where($check);
    }

    /**
     * The parsed files the queries run over.
     *
     * @return list<ParsedFile>
     */
    public function files(): array
    {
        return $this->files;
    }

    /**
     * The SOURCE a file was parsed from — the overlay-aware content, so a scribe splices
     * against exactly the bytes its node offsets index, never a stale `file_get_contents`
     * (which, mid-`repent`, would predate the same run's earlier edits). Null when the
     * codebase doesn't hold that path.
     */
    public function sourceOf(string $path): ?string
    {
        $this->sourceByPath ??= array_reduce(
            $this->files,
            static function (array $map, ParsedFile $file): array {
                $map[$file->path] = $file->source;

                return $map;
            },
            [],
        );

        return $this->sourceByPath[$path] ?? null;
    }

    /**
     * Does $class extend $parent (directly or transitively) within the codebase?
     */
    public function extends(?string $class, string $parent): bool
    {
        if ($class === null) {
            return false;
        }

        $class = ltrim($class, '\\');
        $parent = ltrim($parent, '\\');
        $parents = $this->parentMap();
        $seen = [];

        while (isset($parents[$class]) && ! isset($seen[$class])) {
            $seen[$class] = true;
            $class = $parents[$class];

            if ($class === $parent) {
                return true;
            }
        }

        return false;
    }

    /**
     * Does $class implement $interface — declared directly, inherited from a parent class up the
     * extends chain, or reached through an interface that EXTENDS it? The whole graph is walked,
     * because marking a family with a base contract (`interface ClientAction extends Bedrock`) is
     * how code says what it IS, and a rule that classifies by type must see through the
     * intermediate (#448).
     */
    public function implements(?string $class, string $interface): bool
    {
        if ($class === null) {
            return false;
        }

        $interface = ltrim($interface, '\\');
        $interfaces = $this->interfaceMap();
        $parents = $this->parentMap();
        $queue = [ltrim($class, '\\')];
        $seen = [];

        while (($current = array_pop($queue)) !== null) {
            if (isset($seen[$current])) {
                continue;
            }

            $seen[$current] = true;

            foreach ($interfaces[$current] ?? [] as $contract) {
                if ($contract === $interface) {
                    return true;
                }

                $queue[] = $contract; // An interface carries the contracts IT extends.
            }

            if (isset($parents[$current])) {
                $queue[] = $parents[$current];
            }
        }

        return false;
    }

    /**
     * Does `$class::$method` OVERRIDE a method declared by an ancestor (a parent
     * class or an implemented interface) — so its return type is the ancestor's
     * contract, not the author's to change? Resolved via reflection when the class
     * is autoloadable (catching a vendor ancestor), else via the parsed class graph
     * (an in-codebase ancestor) — mirroring how the engine resolves `isA`.
     */
    public function overridesMethod(?string $class, string $method): bool
    {
        if ($class === null || $method === '') {
            return false;
        }

        $class = ltrim($class, '\\');

        if (class_exists($class)) {
            $parent = get_parent_class($class);

            if ($parent !== false && method_exists($parent, $method)) {
                return true;
            }

            foreach (class_implements($class) ?: [] as $interface) {
                if (method_exists($interface, $method)) {
                    return true;
                }
            }

            return false;
        }

        return $this->ancestorDeclares($class, $method, []);
    }

    /**
     * Does any ancestor of $class — a parent class OR an implemented interface, transitively — declare
     * $method in the PARSED graph? The reflection path above answers for autoloadable code; this is the
     * same question over the codebase's own declarations, interfaces included (an interface contract is
     * every bit as binding on a name as a parent's).
     *
     * @param  array<string, true>  $seen
     */
    private function ancestorDeclares(string $class, string $method, array $seen): bool
    {
        if (isset($seen[$class])) {
            return false;
        }

        $seen[$class] = true;

        foreach ([...(array) ($this->parentMap()[$class] ?? []), ...($this->interfaceMap()[$class] ?? [])] as $ancestor) {
            if (($this->declarationMap()[$ancestor] ?? null)?->node?->getMethod($method) !== null) {
                return true;
            }

            if ($this->ancestorDeclares($ancestor, $method, $seen)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The class declaration for $fqcn as a fluent {@see AstNode}, or an empty node when the
     * class is a builtin or lives outside the scanned tree — so a caller resolving a field's
     * type can ask the declaration questions (`constructorRequiresNoArguments()`) without a
     * `?->`. Names arrive fully-qualified from the parser; a leading `\` is tolerated.
     */
    public function classNamed(?string $fqcn): AstNode
    {
        if ($fqcn === null) {
            return new AstNode();
        }

        return new AstNode($this->classNodeMap()[ltrim($fqcn, '\\')] ?? null);
    }

    /**
     * Does `$fqcn::$method` DECLARE a nullable return type (`?T` / `T|null`)? Resolved to the
     * class (or trait) that actually declares the method, so an inherited method reads its
     * base's signature. False when the class or method isn't known to this codebase.
     */
    public function methodReturnsNullable(?string $fqcn, string $method): bool
    {
        $owner = Support\TypeResolver::forCodebase($this)->declaringClassOfMethod($fqcn, $method);
        $declaration = $this->classNamed($owner)->node;

        return $declaration instanceof ClassLike
            && TypeName::isNullable($declaration->getMethod($method)?->returnType);
    }

    /**
     * The declaration of ANY class-like $fqcn — class, ENUM, interface or trait — as a {@see NodeMatch} that
     * knows its file, or null when it lives outside the scanned tree. Unlike {@see classNamed} (classes only,
     * for container-type resolution), this resolves an enum too and carries the file, so a caller can read or
     * REWRITE the declaration (stamp an attribute on the nested type it points at).
     */
    public function declarationMatch(?string $fqcn): ?NodeMatch
    {
        if ($fqcn === null) {
            return null;
        }

        return $this->declarationMap()[ltrim($fqcn, '\\')] ?? null;
    }

    /**
     * @return array<string, NodeMatch>  class-like FQCN => its match (node + file)
     */
    private function declarationMap(): array
    {
        if ($this->declarationMap !== null) {
            return $this->declarationMap;
        }

        $map = [];
        $finder = new NodeFinder;

        foreach ($this->files as $file) {
            foreach ($finder->findInstanceOf($file->ast, ClassLike::class) as $declaration) {
                /**
                 * @var ClassLike $declaration
                 */
                if (($declaration->namespacedName ?? null) !== null) {
                    $map[$declaration->namespacedName->toString()] = $this->wrap($declaration, $file);
                }
            }
        }

        return $this->declarationMap = $map;
    }

    /**
     * Is $fqcn (or anything descending from it) ever brought to life — `new X` or a static call named
     * among $statically? An object is always CONSTRUCTED before it is used, so this answers "can
     * anything in this codebase produce one of these" for a dispatchable event, a job, a command.
     *
     * @param  list<string>  $statically  constructor-free spellings (`dispatch`, `broadcast`, …)
     */
    public function isEverProduced(string $fqcn, array $statically = []): bool
    {
        $produced = $this->whereNew()
            ->where(fn (AstNode $node) => $this->isA($node->newClassName(), $fqcn))
            ->count() > 0;

        if ($produced || $statically === []) {
            return $produced;
        }

        return $this->whereStaticCall()
            ->where(static fn (AstNode $node): bool => in_array($node->staticCallMethod() ?? '', $statically, true))
            ->where(fn (AstNode $node) => $this->isA($node->staticCallClass(), $fqcn))
            ->count() > 0;
    }

    /**
     * Is $class the SAME as $base, or a descendant of it (extends or implements, walked)? The one home
     * for "does this type answer for that one" — a listener bound to a base event answers every child,
     * an exemption clause covers every subclass of the type it names.
     */
    public function isA(?string $class, string $base): bool
    {
        return $class !== null
            && ($class === $base || $this->extends($class, $base) || $this->implements($class, $base));
    }

    /**
     * Is this type a VALUE — data you hold — rather than a SERVICE you call? A scalar/array/enum is a value;
     * a class is a value only when, WALKING THE CHAIN into it, every one of its own fields is itself a value
     * (a `SocketRef` of scalars, an `EdgePayload` of `SocketRef`s). A service fails: its type is unresolvable
     * (a vendor `DatabaseManager`) or, walked into, it holds other services. Bounded-depth and cycle-guarded;
     * `mixed`/unresolved conservatively counts as NON-value. The reusable "is this a clump member" primitive.
     *
     * @param  array<string, true>  $visited  FQCNs already on the current walk (cycle guard)
     */
    public function isValueType(?Node $type, int $depth = 4, array $visited = []): bool
    {
        if ($type instanceof NullableType) {
            return $this->isValueType($type->type, $depth, $visited);
        }

        // `T | null` / `T | Optional` — the field holds a value T that may be absent; classify T.
        if ($type instanceof UnionType) {
            $core = array_values(array_filter($type->types, static function (Node $member): bool {
                $name = $member instanceof Name ? $member->getLast() : ($member instanceof Identifier ? $member->toString() : '');

                return strcasecmp($name, 'null') !== 0 && $name !== 'Optional';
            }));

            return count($core) === 1 && $this->isValueType($core[0], $depth, $visited);
        }

        if ($type instanceof Identifier) {
            return in_array($type->toString(), ['string', 'int', 'float', 'bool', 'array', 'iterable'], true);
        }

        $class = TypeName::class($type);

        if ($class === null) {
            return false; // a union of classes, `mixed`, `object`, or a shape we won't vouch for
        }

        if ($this->isEnum($class)) {
            return true;
        }

        $declaration = $this->declarationMatch($class);

        if ($declaration === null || $depth <= 0 || isset($visited[$class])) {
            return false; // vendor/unresolved, too deep, or a cycle — cannot vouch it is a value
        }

        $visited[$class] = true;

        foreach ($declaration->fields() as $field) {
            if ($this->isValueType($field->type, $depth - 1, $visited)) {
                continue;
            }

            return false; // holds a service (or something we can't vouch for) → this is a service, not a value
        }

        return true;
    }

    /**
     * Is the class NAMED here a value rather than a service — the same walk {@see isValueType} does over
     * a type declaration, asked of a class directly. The seam a caller needs when it holds a declaration
     * (a `whereClass()` match) instead of a type node, so nobody has to build a synthetic `Name` to ask.
     */
    public function classIsValueType(?string $fqcn): bool
    {
        return $fqcn !== null && $this->isValueType(new Name($fqcn));
    }

    /**
     * @return array<string, Class_>  class FQCN => declaration node
     */
    private function classNodeMap(): array
    {
        if ($this->classNodeMap !== null) {
            return $this->classNodeMap;
        }

        $map = [];
        $finder = new NodeFinder;

        foreach ($this->files as $file) {
            foreach ($finder->findInstanceOf($file->ast, Class_::class) as $class) {
                /**
                 * @var Class_ $class
                 */
                if (($class->namespacedName ?? null) !== null) {
                    $map[$class->namespacedName->toString()] = $class;
                }
            }
        }

        return $this->classNodeMap = $map;
    }

    /**
     * Is $class declared as an enum in the codebase? An enum is a behaviour-bearing
     * value, not a container to resolve from.
     */
    public function isEnum(?string $class): bool
    {
        if ($class === null) {
            return false;
        }

        return in_array(ltrim($class, '\\'), $this->enumNames(), true);
    }

    /**
     * Is $class declared as an INTERFACE in the codebase? An interface is an extension point: whoever
     * implements it may live outside the scanned tree entirely (a library's host application), so no
     * reachability claim about it can be settled from this tree alone.
     */
    public function isInterface(?string $class): bool
    {
        return $this->declarationMatch($class)?->isInterfaceDeclaration() ?? false;
    }

    /**
     * @return list<string>  every enum FQCN declared in the codebase
     */
    private function enumNames(): array
    {
        if ($this->enumNames !== null) {
            return $this->enumNames;
        }

        $names = [];
        $finder = new NodeFinder;

        foreach ($this->files as $file) {
            foreach ($finder->findInstanceOf($file->ast, Enum_::class) as $enum) {
                if (($enum->namespacedName ?? null) !== null) {
                    $names[] = $enum->namespacedName->toString();
                }
            }
        }

        return $this->enumNames = $names;
    }

    /**
     * Is $class extended by any class in the codebase — i.e. a base, not a leaf?
     * A class with subclasses cannot be `final`.
     */
    public function hasSubclass(?string $class): bool
    {
        if ($class === null) {
            return false;
        }

        return in_array(ltrim($class, '\\'), $this->parentMap(), true);
    }

    /**
     * @return array<string, string>  child FQCN => parent FQCN
     *
     * The class-likes that `use` the given trait (directly) — "who consumes this trait",
     * the reverse of a `TraitUse`. Empty when nothing uses it or the name isn't a trait.
     *
     * @return list<string>
     */
    public function usersOfTrait(?string $trait): array
    {
        if ($trait === null) {
            return [];
        }

        return $this->traitUserMap()[ltrim($trait, '\\')] ?? [];
    }

    /**
     * @return array<string, list<string>>
     */
    private function traitUserMap(): array
    {
        if ($this->traitUserMap !== null) {
            return $this->traitUserMap;
        }

        $map = [];
        $finder = new NodeFinder;

        foreach ($this->files as $file) {
            foreach ($finder->findInstanceOf($file->ast, ClassLike::class) as $class) {
                $user = ($class->namespacedName ?? null)?->toString();

                if ($user === null) {
                    continue;
                }

                foreach ($class->getTraitUses() as $use) {
                    foreach ($use->traits as $trait) {
                        $map[ltrim($trait->toString(), '\\')][] = $user;
                    }
                }
            }
        }

        return $this->traitUserMap = $map;
    }

    private function parentMap(): array
    {
        if ($this->parentMap !== null) {
            return $this->parentMap;
        }

        $map = [];
        $finder = new NodeFinder;

        foreach ($this->files as $file) {
            foreach ($finder->find($file->ast, static fn (Node $node): bool => $node instanceof Class_) as $class) {
                /**
                 * @var Class_ $class
                 */
                if ($class->extends instanceof Name && ($class->namespacedName ?? null) !== null) {
                    $map[$class->namespacedName->toString()] = $class->extends->toString();
                }
            }
        }

        return $this->parentMap = $map;
    }

    /**
     * The contract graph: what each declaration answers for DIRECTLY — a class or enum through its
     * `implements`, and an interface through the interfaces it EXTENDS, which is the same edge under
     * another keyword and is what lets {@see implements} see through a base contract.
     *
     * @return array<string, list<string>>  declaration FQCN => the interface FQCNs it names directly
     */
    private function interfaceMap(): array
    {
        if ($this->interfaceMap !== null) {
            return $this->interfaceMap;
        }

        $map = [];
        $finder = new NodeFinder;

        foreach ($this->files as $file) {
            foreach ($finder->find($file->ast, static fn (Node $node): bool => $node instanceof Class_ || $node instanceof Enum_ || $node instanceof Interface_) as $declaration) {
                /**
                 * @var Class_|Enum_|Interface_ $declaration
                 */
                if (($declaration->namespacedName ?? null) === null) {
                    continue;
                }

                $contracts = $declaration instanceof Interface_ ? $declaration->extends : $declaration->implements;

                $map[$declaration->namespacedName->toString()] = array_map(
                    static fn (Name $name): string => $name->toString(),
                    $contracts,
                );
            }
        }

        return $this->interfaceMap = $map;
    }

    private static function typeContains(?Node $type, string $want): bool
    {
        if ($type instanceof Name) {
            return $type->toString() === $want;
        }

        if ($type instanceof NullableType) {
            return self::typeContains($type->type, $want);
        }

        if ($type instanceof UnionType || $type instanceof IntersectionType) {
            foreach ($type->types as $member) {
                if (self::typeContains($member, $want)) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function parse(string $code, string $path): ParsedFile
    {
        try {
            $ast = (new ParserFactory)->createForNewestSupportedVersion()->parse($code) ?? [];
            $traverser = new NodeTraverser(new NameResolver, new ParentConnectingVisitor);

            return new ParsedFile($path, $traverser->traverse($ast), $code);
        } catch (\Throwable $failure) {
            // Reading ONE file must never end a scan of thousands. A syntax error is the obvious
            // case, but RESOLVING names throws too — a duplicate `use` alias is refused by the name
            // resolver mid-traversal, long after the syntax parsed — and a deep enough tree can
            // exhaust the stack. Any of them costs this file only, and says so rather than skipping
            // in silence.
            fwrite(STDERR, "⚠ skipped {$path} — it could not be read: {$failure->getMessage()}\n");

            return new ParsedFile($path, [], $code);
        }
    }

    /**
     * @return iterable<string>
     */
    private static function phpFilesIn(string $path, ExcludedPaths $excluded = new ExcludedPaths()): iterable
    {
        if (is_file($path)) {
            if (! $excluded->covers($path)) {
                yield $path;
            }

            return;
        }

        if (! is_dir($path) || $excluded->covers($path)) {
            return;
        }

        $directory = new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS);

        // Never descend into dependency / VCS / test / tooling trees — they aren't
        // app code under review, and parsing them all exhausts memory on a
        // project-root scan. (`tests`, `.claude`, etc. are excluded by default.)
        $pruned = new RecursiveCallbackFilterIterator($directory, static function (\SplFileInfo $file) use ($excluded): bool {
            if (! $file->isDir()) {
                return true;
            }

            // Never descend a symlinked directory — it can point back up the tree
            // (or at itself) and recurse forever.
            if ($file->isLink()) {
                return false;
            }

            $name = $file->getFilename();

            // Hidden dirs (.git, .idea, .claude, …) are tooling, not source.
            if (str_starts_with($name, '.')) {
                return false;
            }

            // An excluded subtree is pruned HERE, not filtered out of the findings later: a
            // monorepo's build output is megabytes the run would otherwise read and parse in full
            // before discarding every sin it found there.
            return ! in_array($name, self::SKIP_DIRS, true) && ! $excluded->covers($file->getPathname());
        });

        foreach (new RecursiveIteratorIterator($pruned) as $file) {
            if (PhpFile::is($file)) {
                yield $file->getPathname();
            }
        }
    }
}
