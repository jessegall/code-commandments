<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast;

use FilesystemIterator;
use PhpParser\Node;
use PhpParser\Node\Attribute;
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
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Enum_;
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
     */
    /** @var array<string, string>|null  child FQCN => parent FQCN */
    private ?array $parentMap = null;

    /** @var array<string, list<string>>|null  class FQCN => directly-implemented interface FQCNs */
    private ?array $interfaceMap = null;

    /** @var array<string, Class_>|null  class FQCN => declaration node */
    private ?array $classNodeMap = null;

    /** @var list<string>|null  every enum FQCN in the codebase */
    private ?array $enumNames = null;

    private ?CodebaseIndex $index = null;

    /** @var array<class-string<Node>, list<array{0: Node, 1: ParsedFile}>>|null */
    private ?array $nodeBuckets = null;

    private function __construct(private readonly array $files) {}

    /**
     * The call graph over these files (who calls what, receiver types). Built once.
     */
    public function index(): CodebaseIndex
    {
        return $this->index ??= new CodebaseIndex($this);
    }

    /**
     * Every [node, file] pair of the given exact node classes — pulled from an
     * index bucketed by node type and walked ONCE, then cached for the codebase's
     * life. Pass null for every node.
     *
     * This is the engine's anti-quadratic guarantee: a selector visits only the
     * nodes of its own kind (every `new`, every parameter, …), so a query run once
     * per candidate scans a small bucket — not the whole tree on every call.
     *
     * @param  list<class-string<Node>>|null  $types
     * @return iterable<array{0: Node, 1: ParsedFile}>
     */
    public function nodes(?array $types = null): iterable
    {
        $buckets = $this->nodeBuckets ??= $this->bucketNodes();

        foreach ($types ?? array_keys($buckets) as $type) {
            yield from $buckets[$type] ?? [];
        }
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
            foreach ($finder->find($file->ast, static fn (): bool => true) as $node) {
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
     * @param  \Closure(int, int): void|null  $onProgress
     */
    public static function scan(string|array $path, ?\Closure $onProgress = null): self
    {
        $paths = [];

        foreach ((array) $path as $root) {
            foreach (self::phpFilesIn($root) as $file) {
                $paths[$file] = true;
            }
        }

        $paths = array_keys($paths);
        $total = count($paths);
        $files = [];

        foreach ($paths as $index => $file) {
            $code = @file_get_contents($file);

            if ($code !== false) {
                $files[] = self::parse($code, $file);
            }

            if ($onProgress !== null) {
                $onProgress($index + 1, $total);
            }
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
        return $this->whereNew()->where(fn (AstNode $node): bool => $this->extends($node->newClassName(), $parent));
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
     * Every class declaration. Refine with `extends`/predicates on the node.
     */
    public function whereClass(): Query
    {
        return new Query($this, static fn (Node $node): bool => $node instanceof Class_, [Class_::class]);
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
     * Class declarations extending $parent (directly or transitively) — the
     * declaration-side mirror of {@see whereNewExtending}.
     */
    public function whereClassExtending(string $parent): Query
    {
        return $this->whereClass()->where(fn (AstNode $node): bool => $this->extends($node->enclosingClassName(), $parent));
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

            return $resolved === $want || self::shortName($resolved) === $want;
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
     * Open a pattern selecting every node, checked by your own predicate over a
     * fluent {@see AstNode}. Chain more `where`/`reject` to refine.
     *
     * @param  \Closure(AstNode): bool  $check
     */
    public function where(\Closure $check): Query
    {
        return (new Query($this, static fn (Node $node): bool => true))->where($check);
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
     * Does $class implement $interface — declared directly, or inherited from a
     * parent class up the extends chain? (Interface-extends-interface is not walked;
     * the common case is a class declaring the contract it fulfils, e.g. a cast
     * implementing `CastsAttributes`.)
     */
    public function implements(?string $class, string $interface): bool
    {
        if ($class === null) {
            return false;
        }

        $class = ltrim($class, '\\');
        $interface = ltrim($interface, '\\');
        $interfaces = $this->interfaceMap();
        $parents = $this->parentMap();
        $seen = [];

        while ($class !== null && ! isset($seen[$class])) {
            $seen[$class] = true;

            if (in_array($interface, $interfaces[$class] ?? [], true)) {
                return true;
            }

            $class = $parents[$class] ?? null;
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

        $parents = $this->parentMap();
        $nodes = $this->classNodeMap();
        $seen = [];
        $current = $parents[$class] ?? null;

        while ($current !== null && ! isset($seen[$current])) {
            $seen[$current] = true;

            if (($nodes[$current] ?? null)?->getMethod($method) !== null) {
                return true;
            }

            $current = $parents[$current] ?? null;
        }

        return false;
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
                /** @var Class_ $class */
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
     */
    private function parentMap(): array
    {
        if ($this->parentMap !== null) {
            return $this->parentMap;
        }

        $map = [];
        $finder = new NodeFinder;

        foreach ($this->files as $file) {
            foreach ($finder->find($file->ast, static fn (Node $node): bool => $node instanceof Class_) as $class) {
                /** @var Class_ $class */
                if ($class->extends instanceof Name && ($class->namespacedName ?? null) !== null) {
                    $map[$class->namespacedName->toString()] = $class->extends->toString();
                }
            }
        }

        return $this->parentMap = $map;
    }

    /**
     * @return array<string, list<string>>  class FQCN => directly-implemented interface FQCNs
     */
    private function interfaceMap(): array
    {
        if ($this->interfaceMap !== null) {
            return $this->interfaceMap;
        }

        $map = [];
        $finder = new NodeFinder;

        foreach ($this->files as $file) {
            foreach ($finder->find($file->ast, static fn (Node $node): bool => $node instanceof Class_) as $class) {
                /** @var Class_ $class */
                if (($class->namespacedName ?? null) === null) {
                    continue;
                }

                $map[$class->namespacedName->toString()] = array_map(
                    static fn (Name $name): string => $name->toString(),
                    $class->implements,
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

    private static function shortName(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }

    private static function parse(string $code, string $path): ParsedFile
    {
        try {
            $ast = (new ParserFactory)->createForNewestSupportedVersion()->parse($code) ?? [];
        } catch (\PhpParser\Error) {
            // A file that doesn't parse (a syntax error, a stub, a partial edit) is not
            // worth crashing the whole run for — skip its contents and carry on.
            return new ParsedFile($path, [], $code);
        }

        $traverser = new NodeTraverser(new NameResolver, new ParentConnectingVisitor);

        return new ParsedFile($path, $traverser->traverse($ast), $code);
    }

    /**
     * @return iterable<string>
     */
    private static function phpFilesIn(string $path): iterable
    {
        if (is_file($path)) {
            yield $path;

            return;
        }

        if (! is_dir($path)) {
            return;
        }

        $directory = new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS);

        // Never descend into dependency / VCS / test / tooling trees — they aren't
        // app code under review, and parsing them all exhausts memory on a
        // project-root scan. (`tests`, `.claude`, etc. are excluded by default.)
        $pruned = new RecursiveCallbackFilterIterator($directory, static function (\SplFileInfo $file): bool {
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

            return ! in_array($name, self::SKIP_DIRS, true);
        });

        foreach (new RecursiveIteratorIterator($pruned) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                yield $file->getPathname();
            }
        }
    }
}
