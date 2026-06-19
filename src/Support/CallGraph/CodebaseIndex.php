<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\CallGraph;

use PhpParser\Error as ParseError;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use JesseGall\PhpTypes\T_String;

/**
 * Cross-file call-graph and class-shape index, built once per scroll run.
 *
 * Parses every file exactly once, distils class + method summaries, and
 * indexes every call site keyed by the callee's fully qualified name so
 * the OriginTracer can walk "who calls Class::method?" in O(1).
 *
 * AST is dropped after extraction — the stored data is small primitives
 * plus value objects suitable for a 1000+ file codebase.
 */
final class CodebaseIndex
{
    /**
     * External-origin allowlist: if a local variable was assigned from one
     * of these expressions, the containing method is treated as the DTO
     * introduction point.
     *
     * @var array<string, string>  fingerprint => reason label
     */
    private const EXTERNAL_FUNC_ORIGINS = [
        'json_decode' => 'json_decode',
        'file_get_contents' => 'file_get_contents',
        'request' => 'request()',
    ];

    /**
     * Smallest group size the index records. Kept permissive (below the
     * prophet's default `min_group` of 3) so a lowered config still finds
     * its occurrences — the prophet re-applies its own threshold.
     */
    private const ENUM_GROUP_MIN_FLOOR = 2;

    /** Smallest method body (printed lines) the index fingerprints for duplicate detection. */
    private const DUPLICATE_MIN_LINES = 4;

    /** @var array<string, ClassSummary> */
    private array $classes = [];

    /** @var array<string, list<string>>  trait FQCN => lowercased method names */
    private array $traitMethods = [];

    /** @var array<string, EnumSummary>   key = enum FQCN */
    private array $enumsByFqcn = [];

    /** @var array<string, list<EnumSummary>>   key = lowercased short name */
    private array $enumsByShortName = [];

    /** @var array<string, list<CallSite>>   key = "FQCN::method" */
    private array $callersByCallee = [];

    /** @var array<string, list<array{file: string, line: int, in_class: string, in_method: string}>>   key = newed-class FQCN */
    private array $instantiationsByClass = [];

    /** @var array<string, list<array{file: string, line: int}>>   key = fallback-expression fingerprint */
    private array $fallbacksByFingerprint = [];

    /** @var array<string, list<array{file: string, line: int}>>   key = canonical enum-case-group key */
    private array $enumCaseGroupsByKey = [];

    /** @var array<string, list<array{file: string, class: string, method: string, line: int, lines: int}>>   key = method-body hash */
    private array $methodBodiesByHash = [];

    /** @var array<string, list<array{file: string, class: string, method: string, line: int, lines: int}>>   key = leading-fragment hash */
    private array $methodFragmentsByHash = [];

    /**
     * Build from an iterable of file paths. Parse failures are swallowed —
     * partial indices are still useful.
     *
     * @param  iterable<string>|iterable<\SplFileInfo>  $files
     */
    public static function build(iterable $files): self
    {
        $instance = new self();
        $classesPass1 = [];
        self::parseFiles($files, $instance, $classesPass1);
        $shells = self::buildClassShells($classesPass1);
        self::buildMethodsAndClasses($shells, $instance);

        return $instance;
    }

    /**
     * Parse all PHP files and collect class/enum definitions and cross-file metadata.
     *
     * @param  iterable<string>|iterable<\SplFileInfo>  $files
     * @param  array<array{file: string, namespace: ?string, uses: array<string, string>, class: Node\Stmt\Class_}>  $classesPass1
     */
    private static function parseFiles(iterable $files, self $instance, array &$classesPass1): void
    {
        $parser = (new ParserFactory)->createForNewestSupportedVersion();

        foreach ($files as $file) {
            $path = $file instanceof \SplFileInfo ? $file->getRealPath() : (string) $file;

            if (T_String::isEmpty($path) || $path === false) {
                continue;
            }

            if (! is_file($path)) {
                continue;
            }

            if (pathinfo($path, PATHINFO_EXTENSION) !== 'php') {
                continue;
            }

            $content = @file_get_contents($path);

            if ($content === false || T_String::isEmpty($content)) {
                continue;
            }

            try {
                $ast = $parser->parse($content);
            } catch (ParseError) {
                continue;
            }

            if ($ast === null) {
                continue;
            }

            foreach (self::findClasses($ast) as [$namespace, $uses, $class]) {
                if ($class->name === null) {
                    continue;
                }

                $classesPass1[] = [
                    'file' => $path,
                    'namespace' => $namespace,
                    'uses' => $uses,
                    'class' => $class,
                ];
            }

            foreach (self::findEnums($ast) as [$namespace, $enum]) {
                $summary = self::summariseEnum($namespace, $enum, $path);

                if ($summary === null) {
                    continue;
                }

                $instance->enumsByFqcn[$summary->fqcn] = $summary;
                $instance->enumsByShortName[strtolower($summary->short)][] = $summary;
            }

            foreach (self::findTraits($ast) as [$namespace, $trait]) {
                if ($trait->name === null) {
                    continue;
                }

                $fqcn = ($namespace !== null && $namespace !== '') ? $namespace . '\\' . $trait->name->toString() : $trait->name->toString();
                $methods = [];

                foreach ($trait->getMethods() as $method) {
                    $methods[] = strtolower($method->name->toString());
                }

                $instance->traitMethods[$fqcn] = $methods;
            }

            $fallbackFinder = new NodeFinder;

            foreach ($fallbackFinder->find($ast, static fn (Node $n): bool => FallbackFingerprint::qualifies($n)) as $node) {
                $fingerprint = FallbackFingerprint::fingerprint($node, $content);

                if ($fingerprint === null) {
                    continue;
                }

                $instance->fallbacksByFingerprint[$fingerprint][] = [
                    'file' => $path,
                    'line' => $node->getStartLine(),
                ];
            }

            self::indexEnumCaseGroups($ast, $path, $instance);
        }
    }

    /**
     * Build ClassSummary shells with fqcn, parent, use map, and propertyTypes.
     *
     * @param  array<array{file: string, namespace: ?string, uses: array<string, string>, class: Node\Stmt\Class_}>  $classesPass1
     * @return array<string, array{file: string, namespace: ?string, uses: array<string, string>, propertyTypes: array<string, string>, classNode: Node\Stmt\Class_, parent: ?string}>
     */
    private static function buildClassShells(array $classesPass1): array
    {
        $shells = [];

        foreach ($classesPass1 as $entry) {
            $fqcn = self::classFqcn($entry['namespace'], $entry['class']);

            $parent = $entry['class']->extends !== null
                ? NameResolver::resolve($entry['class']->extends->toString(), $entry['uses'], $entry['namespace'])
                : null;

            $propertyTypes = self::collectPropertyTypes($entry['class'], $entry['uses'], $entry['namespace']);

            $shells[$fqcn] = [
                'file' => $entry['file'],
                'namespace' => $entry['namespace'],
                'uses' => $entry['uses'],
                'propertyTypes' => $propertyTypes,
                'classNode' => $entry['class'],
                'parent' => $parent,
            ];
        }

        return $shells;
    }

    /**
     * Build method summaries and ClassSummary objects in pass 2.
     *
     * @param  array<string, array{file: string, namespace: ?string, uses: array<string, string>, propertyTypes: array<string, string>, classNode: Node\Stmt\Class_, parent: ?string}>  $shells
     */
    private static function buildMethodsAndClasses(array $shells, self $instance): void
    {
        foreach ($shells as $fqcn => $shell) {
            $methods = [];

            foreach ($shell['classNode']->getMethods() as $method) {
                $methods[$method->name->toString()] = self::buildMethodSummary(
                    $fqcn,
                    $method,
                    $shell,
                    $shells,
                    $instance,
                );

                $body = MethodBodyHash::of($method, self::DUPLICATE_MIN_LINES);

                if ($body !== null) {
                    $instance->methodBodiesByHash[$body['hash']][] = [
                        'file' => $shell['file'],
                        'class' => $fqcn,
                        'method' => $method->name->toString(),
                        'line' => $method->getStartLine(),
                        'lines' => $body['lines'],
                    ];
                }

                foreach (MethodBodyHash::leadingFragments($method, self::DUPLICATE_MIN_LINES) as $fragment) {
                    $instance->methodFragmentsByHash[$fragment['hash']][] = [
                        'file' => $shell['file'],
                        'class' => $fqcn,
                        'method' => $method->name->toString(),
                        'line' => $method->getStartLine(),
                        'lines' => $fragment['lines'],
                    ];
                }
            }

            $instance->classes[$fqcn] = new ClassSummary(
                fqcn: $fqcn,
                parent: $shell['parent'],
                useStatements: $shell['uses'],
                propertyTypes: $shell['propertyTypes'],
                methods: $methods,
                filePath: $shell['file'],
                traits: self::collectTraits($shell['classNode'], $shell['uses'], $shell['namespace']),
                interfaces: self::collectInterfaces($shell['classNode'], $shell['uses'], $shell['namespace']),
                scalarPropertyTypes: self::collectScalarPropertyTypes($shell['classNode']),
            );
        }
    }

    public function classByFqcn(string $fqcn): ?ClassSummary
    {
        return $this->classes[$fqcn] ?? null;
    }

    /**
     * Lowercased method names declared on a trait, or null when the trait isn't
     * indexed (outside the scroll / not found).
     *
     * @return list<string>|null
     */
    public function traitMethodNames(string $fqcn): ?array
    {
        return $this->traitMethods[ltrim($fqcn, '\\')] ?? null;
    }

    /**
     * Every indexed class, keyed by FQCN — for walks that need to enumerate the
     * hierarchy (e.g. finding subclasses of a given class).
     *
     * @return array<string, ClassSummary>
     */
    public function classes(): array
    {
        return $this->classes;
    }

    /**
     * Resolved FQCNs of every interface $fqcn implements — directly and inherited
     * through its parent chain.
     *
     * @return list<string>
     */
    public function interfacesOf(string $fqcn): array
    {
        $cursor = ltrim($fqcn, '\\');
        $seen = [];
        $interfaces = [];
        $depth = 0;

        while ($cursor !== null && ! isset($seen[$cursor]) && $depth++ < 16) {
            $seen[$cursor] = true;
            $summary = $this->classes[$cursor] ?? null;

            if ($summary === null) {
                break;
            }

            foreach ($summary->interfaces as $interface) {
                $interfaces[ltrim($interface, '\\')] = true;
            }

            $cursor = $summary->parent !== null ? ltrim($summary->parent, '\\') : null;
        }

        return array_keys($interfaces);
    }

    /**
     * Every indexed class that implements $interfaceFqcn (directly or inherited)
     * — its implementer-set, used to judge how NARROW an interface is.
     *
     * @return list<string>
     */
    public function implementersOf(string $interfaceFqcn): array
    {
        $needle = ltrim($interfaceFqcn, '\\');
        $implementers = [];

        foreach (array_keys($this->classes) as $fqcn) {
            if (in_array($needle, $this->interfacesOf($fqcn), true)) {
                $implementers[] = $fqcn;
            }
        }

        return $implementers;
    }

    /**
     * Every class whose parent chain passes through $fqcn (its subclasses,
     * transitively).
     *
     * @return list<string>  subclass FQCNs
     */
    public function subclassesOf(string $fqcn): array
    {
        $target = ltrim($fqcn, '\\');
        $subclasses = [];

        foreach ($this->classes as $candidate => $summary) {
            $parent = $summary->parent;
            $depth = 0;

            while ($parent !== null && $depth++ < 16) {
                $parent = ltrim($parent, '\\');

                if ($parent === $target) {
                    $subclasses[] = $candidate;

                    break;
                }

                $parent = $this->classes[$parent]->parent ?? null;
            }
        }

        return $subclasses;
    }

    public function enumByFqcn(string $fqcn): ?EnumSummary
    {
        return $this->enumsByFqcn[$fqcn] ?? null;
    }

    /**
     * Every enum whose short name matches (case-insensitively). Multiple
     * enums can share a short name across namespaces — the caller decides
     * which one to use.
     *
     * @return list<EnumSummary>
     */
    public function enumsByShortName(string $short): array
    {
        return $this->enumsByShortName[strtolower($short)] ?? [];
    }

    /**
     * Every enum in the project, regardless of short name.
     *
     * @return array<string, EnumSummary>  fqcn => summary
     */
    public function allEnums(): array
    {
        return $this->enumsByFqcn;
    }

    /**
     * @return list<CallSite>
     */
    public function callersOf(string $calleeFqcn, string $method): array
    {
        return $this->callersByCallee[$calleeFqcn . '::' . $method] ?? [];
    }

    /**
     * Every `new <fqcn>(...)` site found inside the indexed methods.
     *
     * Returns an empty list when the class is known to the index (so the
     * caller can confidently say "never `new`-instantiated"). Returns an
     * empty list ALSO when the class is unknown — callers should consult
     * `classByFqcn()` first if they need to distinguish "definitely zero"
     * from "we don't know about this class".
     *
     * Limitation: only tracks `new` expressions inside methods of indexed
     * classes. `new` calls in top-level scripts or in classes outside the
     * scroll are not seen.
     *
     * @return list<array{file: string, line: int, in_class: string, in_method: string}>
     */
    public function instantiationsOf(string $fqcn): array
    {
        return $this->instantiationsByClass[$fqcn] ?? [];
    }

    /**
     * Every site across the scroll whose fallback expression (`??` / `?:` /
     * full-ternary null check) normalises to the same fingerprint. Used to
     * tell whether a chain is genuinely repeated and worth a named factory.
     *
     * @return list<array{file: string, line: int}>
     */
    public function fallbackOccurrences(string $fingerprint): array
    {
        return $this->fallbacksByFingerprint[$fingerprint] ?? [];
    }

    /**
     * Every site across the scroll where an inline subset of an enum's cases
     * canonicalises to the same group key. Order and repetition of cases
     * inside each array don't matter — the key is the sorted, de-duplicated
     * set of `EnumFqcn::CaseName` strings. Used to tell whether a group is
     * genuinely reused (and so deserves a named accessor on the enum).
     *
     * @return list<array{file: string, line: int}>
     */
    public function enumCaseGroupOccurrences(string $key): array
    {
        return $this->enumCaseGroupsByKey[$key] ?? [];
    }

    /**
     * How many sites across the scroll inline the same enum-case group.
     */
    public function enumCaseGroupCount(string $key): int
    {
        return count($this->enumCaseGroupsByKey[$key] ?? []);
    }

    /**
     * Every method across the scroll whose body fingerprints to $hash — used
     * to surface duplicated code fragments.
     *
     * @return list<array{file: string, class: string, method: string, line: int, lines: int}>
     */
    public function methodBodyOccurrences(string $hash): array
    {
        return $this->methodBodiesByHash[$hash] ?? [];
    }

    /**
     * Every method across the scroll one of whose LEADING statement-run
     * prefixes fingerprints to $hash — used to surface a shared preamble
     * duplicated across two methods that then diverge.
     *
     * @return list<array{file: string, class: string, method: string, line: int, lines: int}>
     */
    public function methodFragmentOccurrences(string $hash): array
    {
        return $this->methodFragmentsByHash[$hash] ?? [];
    }

    // ────────────────────────────────────────────────────────────────
    // Build helpers
    // ────────────────────────────────────────────────────────────────

    /**
     * @param  array<Node>  $ast
     * @return list<array{0: ?string, 1: array<string, string>, 2: Node\Stmt\Class_}>
     */
    private static function findClasses(array $ast): array
    {
        $out = [];

        foreach ($ast as $node) {
            if ($node instanceof Node\Stmt\Namespace_) {
                $ns = $node->name?->toString();
                $uses = self::collectUseStatements($node->stmts);

                foreach ($node->stmts as $stmt) {
                    if ($stmt instanceof Node\Stmt\Class_) {
                        $out[] = [$ns, $uses, $stmt];
                    }
                }
            } elseif ($node instanceof Node\Stmt\Class_) {
                $out[] = [null, self::collectUseStatements($ast), $node];
            }
        }

        return $out;
    }

    /**
     * @param  array<Node>  $ast
     * @return list<array{0: ?string, 1: Node\Stmt\Trait_}>
     */
    private static function findTraits(array $ast): array
    {
        $out = [];

        foreach ($ast as $node) {
            if ($node instanceof Node\Stmt\Namespace_) {
                $ns = $node->name?->toString();

                foreach ($node->stmts as $stmt) {
                    if ($stmt instanceof Node\Stmt\Trait_) {
                        $out[] = [$ns, $stmt];
                    }
                }
            } elseif ($node instanceof Node\Stmt\Trait_) {
                $out[] = [null, $node];
            }
        }

        return $out;
    }

    /**
     * @param  array<Node>  $ast
     * @return list<array{0: ?string, 1: Node\Stmt\Enum_}>
     */
    private static function findEnums(array $ast): array
    {
        $out = [];

        foreach ($ast as $node) {
            if ($node instanceof Node\Stmt\Namespace_) {
                $ns = $node->name?->toString();

                foreach ($node->stmts as $stmt) {
                    if ($stmt instanceof Node\Stmt\Enum_) {
                        $out[] = [$ns, $stmt];
                    }
                }
            } elseif ($node instanceof Node\Stmt\Enum_) {
                $out[] = [null, $node];
            }
        }

        return $out;
    }

    /**
     * Record every qualifying inline enum-case group in the file, keyed by
     * its canonical group key. Groups inside the enum's OWN file are skipped
     * (that file is where the named accessor would live, and named-group
     * method bodies like `return [self::A, self::B]` would otherwise inflate
     * the count). Groups that are the haystack of `in_array`/`array_search`
     * are skipped too — that membership test is the CompareSelf rule's
     * territory.
     *
     * @param  array<Node>  $ast
     */
    private static function indexEnumCaseGroups(array $ast, string $path, self $instance): void
    {
        $finder = new NodeFinder;

        foreach ($ast as $node) {
            $namespace = null;
            $scope = [$node];

            if ($node instanceof Node\Stmt\Namespace_) {
                $namespace = $node->name?->toString();
                $scope = $node->stmts;
            }

            $uses = self::collectUseStatements($scope);

            // FQCNs of enums DEFINED in this scope — arrays of their own cases
            // are the named-group home, not a duplicate inline subset.
            $localEnumFqcns = [];

            foreach ($scope as $stmt) {
                if ($stmt instanceof Node\Stmt\Enum_ && $stmt->name !== null) {
                    $short = $stmt->name->toString();
                    $localEnumFqcns[$namespace !== null && T_String::isNotEmpty($namespace) ? $namespace . '\\' . $short : $short] = true;
                }
            }

            // Membership-test haystacks to exclude.
            $needles = new \SplObjectStorage;

            /** @var array<Expr\FuncCall> $calls */
            $calls = $finder->findInstanceOf($scope, Expr\FuncCall::class);

            foreach ($calls as $call) {
                foreach ($finder->findInstanceOf([$call], Expr\Array_::class) as $array) {
                    assert($array instanceof Expr\Array_);

                    if (EnumCaseGroup::isMembershipNeedle($array, $call)) {
                        $needles->offsetSet($array, null);
                    }
                }
            }

            /** @var array<Expr\Array_> $arrays */
            $arrays = $finder->findInstanceOf($scope, Expr\Array_::class);

            foreach ($arrays as $array) {
                assert($array instanceof Expr\Array_);

                if ($needles->offsetExists($array)) {
                    continue;
                }

                $resolved = EnumCaseGroup::resolve($array, $uses, $namespace, self::ENUM_GROUP_MIN_FLOOR);

                if ($resolved === null) {
                    continue;
                }

                if (isset($localEnumFqcns[$resolved['fqcn']])) {
                    continue;
                }

                $instance->enumCaseGroupsByKey[EnumCaseGroup::canonicalKey($resolved)][] = [
                    'file' => $path,
                    'line' => $array->getStartLine(),
                ];
            }
        }
    }

    private static function summariseEnum(?string $namespace, Node\Stmt\Enum_ $enum, string $file): ?EnumSummary
    {
        if ($enum->name === null) {
            return null;
        }

        $short = $enum->name->toString();
        $fqcn = $namespace !== null && T_String::isNotEmpty($namespace)
            ? $namespace . '\\' . $short
            : $short;

        $backing = $enum->scalarType instanceof Node\Identifier
            ? $enum->scalarType->toString()
            : null;

        $cases = [];

        foreach ($enum->stmts as $stmt) {
            if (! $stmt instanceof Node\Stmt\EnumCase) {
                continue;
            }

            $caseName = $stmt->name->toString();

            if ($backing !== null && $stmt->expr !== null) {
                $value = self::scalarValue($stmt->expr);

                if ($value === null) {
                    continue;
                }

                $cases[(string) $value] = $caseName;
            } else {
                $cases[$caseName] = $caseName;
            }
        }

        return new EnumSummary(
            fqcn: $fqcn,
            short: $short,
            backing: $backing,
            cases: $cases,
            filePath: $file,
        );
    }

    private static function scalarValue(Node\Expr $expr): string|int|null
    {
        if ($expr instanceof Scalar\String_) {
            return $expr->value;
        }

        if ($expr instanceof Scalar\Int_) {
            return $expr->value;
        }

        if ($expr instanceof Expr\UnaryMinus && $expr->expr instanceof Scalar\Int_) {
            return -$expr->expr->value;
        }

        return null;
    }

    /**
     * @param  array<Node>  $stmts
     * @return array<string, string>
     */
    private static function collectUseStatements(array $stmts): array
    {
        $uses = [];

        foreach ($stmts as $stmt) {
            if (! $stmt instanceof Node\Stmt\Use_) {
                continue;
            }

            foreach ($stmt->uses as $useUse) {
                $fqcn = $useUse->name->toString();
                $alias = $useUse->alias?->toString() ?? $useUse->name->getLast();
                $uses[$alias] = $fqcn;
            }
        }

        return $uses;
    }

    private static function classFqcn(?string $namespace, Node\Stmt\Class_ $class): string
    {
        $short = $class->name?->toString() ?? 'Anonymous';

        return $namespace !== null && T_String::isNotEmpty($namespace)
            ? $namespace . '\\' . $short
            : $short;
    }

    /**
     * @param  array<string, string>  $uses
     * @return array<string, string>  propName => FQCN type
     */
    private static function collectPropertyTypes(Node\Stmt\Class_ $class, array $uses, ?string $namespace): array
    {
        $types = [];

        // Declared typed properties
        foreach ($class->getProperties() as $prop) {
            if ($prop->type === null) {
                continue;
            }

            $typeName = NameResolver::typeName($prop->type);

            if ($typeName === null || self::isScalar($typeName)) {
                continue;
            }

            $fqcn = NameResolver::resolve($typeName, $uses, $namespace);

            foreach ($prop->props as $propProp) {
                $types[$propProp->name->toString()] = $fqcn;
            }
        }

        // Constructor-promoted properties
        $ctor = $class->getMethod('__construct');

        if ($ctor !== null) {
            foreach ($ctor->params as $param) {
                if ($param->flags === 0) {
                    continue; // not promoted
                }

                if ($param->type === null) {
                    continue;
                }

                $typeName = NameResolver::typeName($param->type);

                if ($typeName === null || self::isScalar($typeName)) {
                    continue;
                }

                if (! $param->var instanceof Expr\Variable || ! is_string($param->var->name)) {
                    continue;
                }

                $types[$param->var->name] = NameResolver::resolve($typeName, $uses, $namespace);
            }
        }

        return $types;
    }

    /**
     * Property name => scalar type (string/int/float/bool/…) for the properties
     * that {@see collectPropertyTypes()} skips — so a reader can tell a scalar
     * property (an enum's backing value) from an object one.
     *
     * @return array<string, string>
     */
    private static function collectScalarPropertyTypes(Node\Stmt\Class_ $class): array
    {
        $types = [];

        foreach ($class->getProperties() as $prop) {
            $typeName = $prop->type !== null ? NameResolver::typeName($prop->type) : null;

            if ($typeName !== null && self::isScalar($typeName)) {
                foreach ($prop->props as $propProp) {
                    $types[$propProp->name->toString()] = strtolower($typeName);
                }
            }
        }

        $ctor = $class->getMethod('__construct');

        if ($ctor !== null) {
            foreach ($ctor->params as $param) {
                if ($param->flags === 0 || $param->type === null || ! $param->var instanceof Expr\Variable || ! is_string($param->var->name)) {
                    continue;
                }

                $typeName = NameResolver::typeName($param->type);

                if ($typeName !== null && self::isScalar($typeName)) {
                    $types[$param->var->name] = strtolower($typeName);
                }
            }
        }

        return $types;
    }

    /**
     * Resolved FQCNs of every trait the class `use`s.
     *
     * @param  array<string, string>  $uses
     * @return list<string>
     */
    private static function collectTraits(Node\Stmt\Class_ $class, array $uses, ?string $namespace): array
    {
        $traits = [];

        foreach ($class->stmts as $stmt) {
            if (! $stmt instanceof Node\Stmt\TraitUse) {
                continue;
            }

            foreach ($stmt->traits as $trait) {
                $traits[] = NameResolver::resolve($trait->toString(), $uses, $namespace);
            }
        }

        return $traits;
    }

    /**
     * Resolved FQCNs of every interface the class directly implements.
     *
     * @param  array<string, string>  $uses
     * @return list<string>
     */
    private static function collectInterfaces(Node\Stmt\Class_ $class, array $uses, ?string $namespace): array
    {
        $interfaces = [];

        foreach ($class->implements as $implement) {
            $interfaces[] = NameResolver::resolve($implement->toString(), $uses, $namespace);
        }

        return $interfaces;
    }

    private static function isScalar(string $type): bool
    {
        return in_array(strtolower($type), [
            'string', 'int', 'float', 'bool', 'array', 'object',
            'mixed', 'null', 'void', 'never', 'callable', 'iterable',
            'true', 'false', 'self', 'static', 'parent',
        ], true);
    }

    /**
     * @param  array<string, array{file: string, namespace: ?string, uses: array<string, string>, propertyTypes: array<string, string>, classNode: Node\Stmt\Class_, parent: ?string}>  $shells
     * @param  array{file: string, namespace: ?string, uses: array<string, string>, propertyTypes: array<string, string>, classNode: Node\Stmt\Class_, parent: ?string}  $shell
     */
    private static function buildMethodSummary(
        string $classFqcn,
        Node\Stmt\ClassMethod $method,
        array $shell,
        array $shells,
        self $index,
    ): MethodSummary {
        $params = self::buildMethodParams($method, $shell);
        $assignments = [];
        $callSites = [];

        if ($method->stmts !== null) {
            self::buildAssignments($method, $assignments);
            self::buildCallSites($classFqcn, $method, $params, $shell, $shells, $index, $callSites);
            self::buildInstantiations($method, $shell, $shells, $index);
        }

        return new MethodSummary(
            classFqcn: $classFqcn,
            name: $method->name->toString(),
            params: $params,
            callSites: $callSites,
            assignments: $assignments,
            filePath: $shell['file'],
            line: $method->getStartLine(),
            returnInnerType: self::returnInnerType($method, $shell['uses'], $shell['namespace']),
            returnTypeName: self::returnTypeName($method),
            returnDocIsParameterized: self::returnDocIsParameterized($method),
        );
    }

    /**
     * The short name of the method's native return type (`Option`, `array`,
     * `mixed`, …); null when untyped or a union/intersection/nullable-complex.
     */
    private static function returnTypeName(Node\Stmt\ClassMethod $method): ?string
    {
        $type = $method->returnType;

        if ($type instanceof Node\NullableType) {
            $type = $type->type;
        }

        if ($type instanceof Node\Identifier) {
            return strtolower($type->toString());
        }

        return $type instanceof Node\Name ? $type->getLast() : null;
    }

    private static function returnDocIsParameterized(Node\Stmt\ClassMethod $method): bool
    {
        $doc = $method->getDocComment();

        return $doc !== null && preg_match('/@return\s+[\\\\\w]+<[^>]+>/', $doc->getText()) === 1;
    }

    /**
     * The resolved FQCN of the single generic in a `@return Wrapper<Inner>`
     * docblock (e.g. `@return Option<NodeDescriptor>` -> the NodeDescriptor
     * FQCN). Powers unwrap-chain owner resolution (`$opt->getOrThrow()->m()`).
     *
     * @param  array<string, string>  $uses
     */
    private static function returnInnerType(Node\Stmt\ClassMethod $method, array $uses, ?string $namespace): ?string
    {
        $doc = $method->getDocComment();

        if ($doc === null) {
            return null;
        }

        if (preg_match('/@return\s+[\\\\\w]+<\s*([\\\\\w]+)\s*>/', $doc->getText(), $m) !== 1) {
            return null;
        }

        return NameResolver::resolve($m[1], $uses, $namespace);
    }

    /**
     * Build method parameter summaries with resolved types.
     *
     * @param  array{file: string, namespace: ?string, uses: array<string, string>, propertyTypes: array<string, string>, classNode: Node\Stmt\Class_, parent: ?string}  $shell
     * @return list<array{name: string, type: ?string}>
     */
    private static function buildMethodParams(Node\Stmt\ClassMethod $method, array $shell): array
    {
        $params = [];

        foreach ($method->params as $param) {
            if (! $param->var instanceof Expr\Variable || ! is_string($param->var->name)) {
                continue;
            }

            $typeName = NameResolver::typeName($param->type);
            $fqcn = null;

            if ($typeName !== null && ! self::isScalar($typeName)) {
                $fqcn = NameResolver::resolve($typeName, $shell['uses'], $shell['namespace']);
            }

            $params[] = [
                'name' => $param->var->name,
                'type' => $fqcn ?? $typeName,
            ];
        }

        return $params;
    }

    /**
     * Build assignment kind map from all assignments in method body.
     *
     * @param  array<string, array{kind: string, reason?: string}>  $assignments
     */
    private static function buildAssignments(Node\Stmt\ClassMethod $method, array &$assignments): void
    {
        if ($method->stmts === null) {
            return;
        }

        $finder = new NodeFinder;
        /** @var array<Expr\Assign> $assigns */
        $assigns = $finder->findInstanceOf($method->stmts, Expr\Assign::class);

        foreach ($assigns as $assign) {
            if (! $assign->var instanceof Expr\Variable || ! is_string($assign->var->name)) {
                continue;
            }

            $kind = self::classifyAssignmentRhs($assign->expr);

            // Keep the first classification — later reassignments are less
            // informative about where the value was first introduced.
            if (! isset($assignments[$assign->var->name])) {
                $assignments[$assign->var->name] = $kind;
            }
        }
    }

    /**
     * Build call sites (method, nullsafe, and static calls) from method body.
     *
     * @param  list<array{name: string, type: ?string}>  $params
     * @param  array{file: string, namespace: ?string, uses: array<string, string>, propertyTypes: array<string, string>, classNode: Node\Stmt\Class_, parent: ?string}  $shell
     * @param  array<string, array{file: string, namespace: ?string, uses: array<string, string>, propertyTypes: array<string, string>, classNode: Node\Stmt\Class_, parent: ?string}>  $shells
     * @param  list<CallSite>  $callSites
     */
    private static function buildCallSites(
        string $classFqcn,
        Node\Stmt\ClassMethod $method,
        array $params,
        array $shell,
        array $shells,
        self $index,
        array &$callSites,
    ): void {
        if ($method->stmts === null) {
            return;
        }

        $finder = new NodeFinder;
        $methodName = $method->name->toString();

        foreach ($finder->findInstanceOf($method->stmts, Expr\MethodCall::class) as $call) {
            assert($call instanceof Expr\MethodCall);
            $cs = self::buildCallSite($classFqcn, $methodName, $call, $params, $shell, $shells, 'method');
            if ($cs !== null) {
                $callSites[] = $cs;
                $index->registerCallSite($cs);
            }
        }

        foreach ($finder->findInstanceOf($method->stmts, Expr\NullsafeMethodCall::class) as $call) {
            assert($call instanceof Expr\NullsafeMethodCall);
            $cs = self::buildCallSite($classFqcn, $methodName, $call, $params, $shell, $shells, 'nullsafe');
            if ($cs !== null) {
                $callSites[] = $cs;
                $index->registerCallSite($cs);
            }
        }

        foreach ($finder->findInstanceOf($method->stmts, Expr\StaticCall::class) as $call) {
            assert($call instanceof Expr\StaticCall);
            $cs = self::buildCallSite($classFqcn, $methodName, $call, $params, $shell, $shells, 'static');
            if ($cs !== null) {
                $callSites[] = $cs;
                $index->registerCallSite($cs);
            }
        }
    }

    /**
     * Register instantiations (new expressions) found in method body.
     *
     * @param  array{file: string, namespace: ?string, uses: array<string, string>, propertyTypes: array<string, string>, classNode: Node\Stmt\Class_, parent: ?string}  $shell
     * @param  array<string, array{file: string, namespace: ?string, uses: array<string, string>, propertyTypes: array<string, string>, classNode: Node\Stmt\Class_, parent: ?string}>  $shells
     */
    private static function buildInstantiations(
        Node\Stmt\ClassMethod $method,
        array $shell,
        array $shells,
        self $index,
    ): void {
        if ($method->stmts === null) {
            return;
        }

        $finder = new NodeFinder;
        $classFqcn = self::classFqcn($shell['namespace'], $shell['classNode']);

        foreach ($finder->findInstanceOf($method->stmts, Expr\New_::class) as $new) {
            assert($new instanceof Expr\New_);

            if (! $new->class instanceof Node\Name) {
                // Anonymous class or dynamic class — can't resolve.
                continue;
            }

            $newedFqcn = NameResolver::resolve(
                $new->class->toString(),
                $shell['uses'],
                $shell['namespace'],
            );

            // Only register `new` sites for classes we know about — the
            // index is consulted to answer "is THIS class ever `new`d?",
            // and unknown classes wouldn't be queried.
            if (! isset($shells[$newedFqcn])) {
                continue;
            }

            $index->registerInstantiation($newedFqcn, [
                'file' => $shell['file'],
                'line' => $new->getStartLine(),
                'in_class' => $classFqcn,
                'in_method' => $method->name->toString(),
            ]);
        }
    }

    /**
     * @return array{kind: string, reason?: string}
     */
    private static function classifyAssignmentRhs(Node $rhs): array
    {
        if ($rhs instanceof Expr\Array_) {
            return ['kind' => 'array_literal'];
        }

        if ($rhs instanceof Expr\FuncCall && $rhs->name instanceof Node\Name) {
            $funcName = $rhs->name->toString();

            if (isset(self::EXTERNAL_FUNC_ORIGINS[$funcName])) {
                return ['kind' => 'external_origin', 'reason' => self::EXTERNAL_FUNC_ORIGINS[$funcName]];
            }
        }

        if ($rhs instanceof Expr\StaticCall
            && $rhs->class instanceof Node\Name
            && $rhs->name instanceof Node\Identifier
        ) {
            $className = $rhs->class->getLast();
            $methodName = $rhs->name->toString();

            if ($className === 'Request' && in_array($methodName, ['all', 'input', 'json'], true)) {
                return ['kind' => 'external_origin', 'reason' => 'Request::' . $methodName . '()'];
            }

            if ($className === 'DB' && in_array($methodName, ['select', 'selectOne'], true)) {
                return ['kind' => 'external_origin', 'reason' => 'DB::' . $methodName . '()'];
            }
        }

        if ($rhs instanceof Expr\MethodCall && $rhs->name instanceof Node\Identifier) {
            $methodName = $rhs->name->toString();

            if (in_array($methodName, ['all', 'input', 'json'], true)) {
                // Assume receiver is a request-shaped object
                return ['kind' => 'external_origin', 'reason' => '->' . $methodName . '()'];
            }

            if ($methodName === 'toArray') {
                return ['kind' => 'external_origin', 'reason' => '->toArray()'];
            }
        }

        return ['kind' => 'complex'];
    }

    /**
     * @param  list<array{name: string, type: ?string}>  $callerParams
     * @param  array{file: string, namespace: ?string, uses: array<string, string>, propertyTypes: array<string, string>, classNode: Node\Stmt\Class_, parent: ?string}  $shell
     * @param  array<string, array{file: string, namespace: ?string, uses: array<string, string>, propertyTypes: array<string, string>, classNode: Node\Stmt\Class_, parent: ?string}>  $shells
     */
    private static function buildCallSite(
        string $callerClass,
        string $callerMethod,
        Expr $call,
        array $callerParams,
        array $shell,
        array $shells,
        string $kind,
    ): ?CallSite {
        // Resolve callee class FQCN and method name
        if ($call instanceof Expr\MethodCall || $call instanceof Expr\NullsafeMethodCall) {
            if (! $call->name instanceof Node\Identifier) {
                return null;
            }

            $methodName = $call->name->toString();
            $calleeFqcn = self::resolveReceiverType(
                $call->var,
                $callerClass,
                $callerParams,
                $shell,
            );

            if ($calleeFqcn === null) {
                return null;
            }
        } elseif ($call instanceof Expr\StaticCall) {
            if (! $call->name instanceof Node\Identifier) {
                return null;
            }

            if (! $call->class instanceof Node\Name) {
                return null;
            }

            $methodName = $call->name->toString();
            $className = $call->class->toString();

            if ($className === 'self' || $className === 'static') {
                $calleeFqcn = $callerClass;
            } elseif ($className === 'parent') {
                $calleeFqcn = $shell['parent'] ?? null;
            } else {
                $calleeFqcn = NameResolver::resolve($className, $shell['uses'], $shell['namespace']);
            }

            if ($calleeFqcn === null) {
                return null;
            }
        } else {
            return null;
        }

        // Only index calls into classes we know about — out-of-scroll callees
        // wouldn't resolve anyway.
        if (! isset($shells[$calleeFqcn])) {
            return null;
        }

        $argExprs = self::fingerprintArgs($call->args);

        return new CallSite(
            calleeFqcn: $calleeFqcn,
            calleeMethod: $methodName,
            calleeKind: $kind,
            argExprs: $argExprs,
            callerClassFqcn: $callerClass,
            callerMethod: $callerMethod,
            callerFile: $shell['file'],
            line: $call->getStartLine(),
            startFilePos: $call->getStartFilePos(),
        );
    }

    /**
     * @param  list<array{name: string, type: ?string}>  $callerParams
     * @param  array{uses: array<string, string>, namespace: ?string, propertyTypes: array<string, string>, parent: ?string, file: string, classNode: Node\Stmt\Class_}  $shell
     */
    private static function resolveReceiverType(
        Node $receiver,
        string $callerClass,
        array $callerParams,
        array $shell,
    ): ?string {
        if ($receiver instanceof Expr\Variable && is_string($receiver->name)) {
            if ($receiver->name === 'this') {
                return $callerClass;
            }

            foreach ($callerParams as $param) {
                if ($param['name'] !== $receiver->name) {
                    continue;
                }

                $type = $param['type'];

                if ($type === null || self::isScalar($type)) {
                    return null;
                }

                return $type;
            }

            return null;
        }

        if ($receiver instanceof Expr\PropertyFetch
            && $receiver->var instanceof Expr\Variable
            && $receiver->var->name === 'this'
            && $receiver->name instanceof Node\Identifier
        ) {
            return $shell['propertyTypes'][$receiver->name->toString()] ?? null;
        }

        return null;
    }

    /**
     * @param  list<Node\Arg|Node\VariadicPlaceholder>  $args
     * @return list<array{kind: string, name?: string, prop?: string, value?: string, argName?: string}>
     */
    private static function fingerprintArgs(array $args): array
    {
        $out = [];

        foreach ($args as $arg) {
            if (! $arg instanceof Node\Arg) {
                $out[] = ['kind' => 'complex'];
                continue;
            }

            $expr = $arg->value;
            $argName = $arg->name?->toString();

            if ($expr instanceof Scalar\String_) {
                $entry = ['kind' => 'string_literal', 'value' => $expr->value];

                if ($argName !== null) {
                    $entry['argName'] = $argName;
                }

                $out[] = $entry;
                continue;
            }

            if ($expr instanceof Expr\Variable && is_string($expr->name)) {
                $entry = ['kind' => 'var', 'name' => $expr->name];

                if ($argName !== null) {
                    $entry['argName'] = $argName;
                }

                $out[] = $entry;
                continue;
            }

            if ($expr instanceof Expr\PropertyFetch
                && $expr->var instanceof Expr\Variable
                && $expr->var->name === 'this'
                && $expr->name instanceof Node\Identifier
            ) {
                $entry = ['kind' => 'prop', 'prop' => $expr->name->toString()];

                if ($argName !== null) {
                    $entry['argName'] = $argName;
                }

                $out[] = $entry;
                continue;
            }

            $entry = ['kind' => 'complex'];

            if ($argName !== null) {
                $entry['argName'] = $argName;
            }

            $out[] = $entry;
        }

        return $out;
    }

    private function registerCallSite(CallSite $cs): void
    {
        $key = $cs->calleeFqcn . '::' . $cs->calleeMethod;
        $this->callersByCallee[$key][] = $cs;
    }

    /**
     * @param  array{file: string, line: int, in_class: string, in_method: string}  $site
     */
    private function registerInstantiation(string $fqcn, array $site): void
    {
        $this->instantiationsByClass[$fqcn][] = $site;
    }
}
