<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Php;

use JesseGall\CodeCommandments\Support\Pipes\MatchResult;
use JesseGall\CodeCommandments\Support\Pipes\Pipe;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;

/**
 * Find method calls on request objects that should use typed FormRequest getters instead.
 *
 * Uses AST analysis and reflection-based type checking to detect both
 * $request->method() and $this->request->method() patterns.
 *
 * @implements Pipe<PhpContext, PhpContext>
 */
final class FindDirectRequestMethodCalls implements Pipe
{
    /** @var array<string> */
    private array $forbiddenMethods;

    /**
     * @param  array<string>  $forbiddenMethods  Method names to flag
     */
    public function __construct(array $forbiddenMethods)
    {
        $this->forbiddenMethods = $forbiddenMethods;
    }

    public function handle(mixed $input): mixed
    {
        $matches = [];
        $nodeFinder = new NodeFinder;

        foreach ($input->classes as $class) {
            $propertyRequestNames = $this->resolvePropertyRequestNames($class, $input->useStatements, $input->namespace);

            foreach ($class->getMethods() as $method) {
                if ($method->name->toString() === '__construct') {
                    continue;
                }

                $paramRequestNames = $this->resolveMethodParamRequestNames($method, $input->useStatements, $input->namespace);

                /** @var array<Expr\MethodCall> $methodCalls */
                $methodCalls = $nodeFinder->findInstanceOf($method->stmts ?? [], Expr\MethodCall::class);

                foreach ($methodCalls as $call) {
                    if (! $call->name instanceof Node\Identifier) {
                        continue;
                    }

                    $methodName = $call->name->toString();

                    if (! in_array($methodName, $this->forbiddenMethods, true)) {
                        continue;
                    }

                    // Allow empty calls like $request->input() or $request->query() (no arguments)
                    if (in_array($methodName, ['input', 'query'], true) && empty($call->args)) {
                        continue;
                    }

                    if ($this->isRequestObject($call->var, $paramRequestNames, $propertyRequestNames)) {
                        $line = $call->getStartLine();

                        $matches[] = new MatchResult(
                            name: $methodName,
                            pattern: '',
                            match: $methodName,
                            line: $line,
                            offset: null,
                            content: $this->getSnippet($input->content, $line),
                            groups: [$methodName],
                        );
                    }
                }
            }
        }

        return $input->with(matches: $matches);
    }

    /**
     * Check if the expression is a request variable or $this->request property.
     */
    private function isRequestObject(Expr $var, array $paramNames, array $propertyNames): bool
    {
        // $request->method()
        if ($var instanceof Expr\Variable && is_string($var->name)) {
            return in_array($var->name, $paramNames, true);
        }

        // $this->request->method()
        if ($var instanceof Expr\PropertyFetch
            && $var->var instanceof Expr\Variable
            && $var->var->name === 'this'
            && $var->name instanceof Node\Identifier
        ) {
            return in_array($var->name->toString(), $propertyNames, true);
        }

        return false;
    }

    /**
     * Resolve class-level properties and promoted constructor parameters that are request types.
     *
     * @return array<string>
     */
    private function resolvePropertyRequestNames(Stmt\Class_ $class, array $useStatements, ?string $namespace): array
    {
        $names = [];

        // Explicit typed properties
        foreach ($class->getProperties() as $property) {
            if ($property->type === null) {
                continue;
            }

            $typeName = $this->getTypeName($property->type);

            if ($typeName === null) {
                continue;
            }

            $fqcn = $this->resolveFullyQualifiedName($typeName, $useStatements, $namespace);

            if (TypeChecker::isFormRequestType($fqcn)) {
                foreach ($property->props as $prop) {
                    $names[] = $prop->name->toString();
                }
            }
        }

        // Promoted constructor parameters
        $constructor = $class->getMethod('__construct');

        if ($constructor !== null) {
            foreach ($constructor->params as $param) {
                if ($param->flags === 0) {
                    continue;
                }

                if ($param->type === null) {
                    continue;
                }

                $typeName = $this->getTypeName($param->type);

                if ($typeName === null) {
                    continue;
                }

                $fqcn = $this->resolveFullyQualifiedName($typeName, $useStatements, $namespace);

                if (TypeChecker::isFormRequestType($fqcn)) {
                    $names[] = $param->var->name;
                }
            }
        }

        return $names;
    }

    /**
     * Resolve method parameters that are request types.
     *
     * @return array<string>
     */
    private function resolveMethodParamRequestNames(Stmt\ClassMethod $method, array $useStatements, ?string $namespace): array
    {
        $names = [];

        foreach ($method->params as $param) {
            if ($param->type === null) {
                continue;
            }

            $typeName = $this->getTypeName($param->type);

            if ($typeName === null) {
                continue;
            }

            $fqcn = $this->resolveFullyQualifiedName($typeName, $useStatements, $namespace);

            if (TypeChecker::isFormRequestType($fqcn)) {
                $names[] = $param->var->name;
            }
        }

        return $names;
    }

    private function getTypeName(?Node $type): ?string
    {
        if ($type instanceof Node\Name) {
            return $type->toString();
        }

        if ($type instanceof Node\Identifier) {
            return $type->toString();
        }

        if ($type instanceof Node\NullableType) {
            return $this->getTypeName($type->type);
        }

        if ($type instanceof Node\UnionType || $type instanceof Node\IntersectionType) {
            return $this->getTypeName($type->types[0] ?? null);
        }

        return null;
    }

    /**
     * @param  array<string, string>  $useStatements
     */
    private function resolveFullyQualifiedName(string $typeName, array $useStatements, ?string $namespace): string
    {
        if (str_starts_with($typeName, '\\')) {
            return ltrim($typeName, '\\');
        }

        $parts = explode('\\', $typeName);
        $firstPart = $parts[0];

        if (isset($useStatements[$firstPart])) {
            if (count($parts) === 1) {
                return $useStatements[$firstPart];
            }

            $parts[0] = $useStatements[$firstPart];

            return implode('\\', $parts);
        }

        if ($namespace) {
            return $namespace . '\\' . $typeName;
        }

        return $typeName;
    }

    private function getSnippet(string $content, int $line): string
    {
        $lines = explode("\n", $content);

        return isset($lines[$line - 1]) ? trim($lines[$line - 1]) : '';
    }
}
