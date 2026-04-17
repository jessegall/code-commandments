<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\CallGraph;

use PhpParser\Error as ParseError;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

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

    /** @var array<string, ClassSummary> */
    private array $classes = [];

    /** @var array<string, list<CallSite>>   key = "FQCN::method" */
    private array $callersByCallee = [];

    /**
     * Build from an iterable of file paths. Parse failures are swallowed —
     * partial indices are still useful.
     *
     * @param  iterable<string>|iterable<\SplFileInfo>  $files
     */
    public static function build(iterable $files): self
    {
        $instance = new self();
        $parser = (new ParserFactory)->createForNewestSupportedVersion();

        /** @var array<array{file: string, namespace: ?string, uses: array<string, string>, class: Node\Stmt\Class_}> $classesPass1 */
        $classesPass1 = [];

        foreach ($files as $file) {
            $path = $file instanceof \SplFileInfo ? $file->getRealPath() : (string) $file;

            if ($path === '' || $path === false) {
                continue;
            }

            if (! is_file($path)) {
                continue;
            }

            if (pathinfo($path, PATHINFO_EXTENSION) !== 'php') {
                continue;
            }

            $content = @file_get_contents($path);

            if ($content === false || $content === '') {
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
        }

        // Pass 1: build ClassSummary shells (fqcn, parent, use map, propertyTypes)
        //         and MethodSummaries with UNRESOLVED call sites temporarily keyed by
        //         the expression shape.
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

        // Pass 2: walk each method, resolve call sites using the populated shells.
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
            }

            $instance->classes[$fqcn] = new ClassSummary(
                fqcn: $fqcn,
                parent: $shell['parent'],
                useStatements: $shell['uses'],
                propertyTypes: $shell['propertyTypes'],
                methods: $methods,
                filePath: $shell['file'],
            );
        }

        return $instance;
    }

    public function classByFqcn(string $fqcn): ?ClassSummary
    {
        return $this->classes[$fqcn] ?? null;
    }

    /**
     * @return list<CallSite>
     */
    public function callersOf(string $calleeFqcn, string $method): array
    {
        return $this->callersByCallee[$calleeFqcn . '::' . $method] ?? [];
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

        return $namespace !== null && $namespace !== ''
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

        $finder = new NodeFinder;
        $assignments = [];
        $callSites = [];

        if ($method->stmts !== null) {
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

            foreach ($finder->findInstanceOf($method->stmts, Expr\MethodCall::class) as $call) {
                assert($call instanceof Expr\MethodCall);
                $cs = self::buildCallSite(
                    $classFqcn, $method->name->toString(),
                    $call, $params, $shell, $shells, 'method',
                );

                if ($cs !== null) {
                    $callSites[] = $cs;
                    $index->registerCallSite($cs);
                }
            }

            foreach ($finder->findInstanceOf($method->stmts, Expr\NullsafeMethodCall::class) as $call) {
                assert($call instanceof Expr\NullsafeMethodCall);
                $cs = self::buildCallSite(
                    $classFqcn, $method->name->toString(),
                    $call, $params, $shell, $shells, 'nullsafe',
                );

                if ($cs !== null) {
                    $callSites[] = $cs;
                    $index->registerCallSite($cs);
                }
            }

            foreach ($finder->findInstanceOf($method->stmts, Expr\StaticCall::class) as $call) {
                assert($call instanceof Expr\StaticCall);
                $cs = self::buildCallSite(
                    $classFqcn, $method->name->toString(),
                    $call, $params, $shell, $shells, 'static',
                );

                if ($cs !== null) {
                    $callSites[] = $cs;
                    $index->registerCallSite($cs);
                }
            }
        }

        return new MethodSummary(
            classFqcn: $classFqcn,
            name: $method->name->toString(),
            params: $params,
            callSites: $callSites,
            assignments: $assignments,
            filePath: $shell['file'],
            line: $method->getStartLine(),
        );
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
     * @return list<array{kind: string, name?: string, prop?: string}>
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

            if ($expr instanceof Expr\Variable && is_string($expr->name)) {
                $out[] = ['kind' => 'var', 'name' => $expr->name];
                continue;
            }

            if ($expr instanceof Expr\PropertyFetch
                && $expr->var instanceof Expr\Variable
                && $expr->var->name === 'this'
                && $expr->name instanceof Node\Identifier
            ) {
                $out[] = ['kind' => 'prop', 'prop' => $expr->name->toString()];
                continue;
            }

            $out[] = ['kind' => 'complex'];
        }

        return $out;
    }

    private function registerCallSite(CallSite $cs): void
    {
        $key = $cs->calleeFqcn . '::' . $cs->calleeMethod;
        $this->callersByCallee[$key][] = $cs;
    }
}
