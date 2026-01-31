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
 * Find cases where request method calls are passed as values in Data::from() arrays.
 *
 * Detects patterns like:
 *     SomePage::from([
 *         'key' => $request->getPreviewColumns(),
 *     ])
 *
 * @implements Pipe<PhpContext, PhpContext>
 */
final class FindRequestToDataPassthrough implements Pipe
{
    public function handle(mixed $input): mixed
    {
        $matches = [];
        $nodeFinder = new NodeFinder;

        foreach ($input->classes as $class) {
            $propertyRequestNames = $this->resolvePropertyRequestNames($class, $input->useStatements, $input->namespace);

            foreach ($class->getMethods() as $method) {
                $paramRequestNames = $this->resolveMethodParamRequestNames($method, $input->useStatements, $input->namespace);
                $allRequestNames = array_merge($paramRequestNames, $propertyRequestNames);

                /** @var array<Expr\StaticCall> $staticCalls */
                $staticCalls = $nodeFinder->findInstanceOf($method->stmts ?? [], Expr\StaticCall::class);

                foreach ($staticCalls as $call) {
                    if (! $call->name instanceof Node\Identifier || $call->name->toString() !== 'from') {
                        continue;
                    }

                    if (empty($call->args)) {
                        continue;
                    }

                    $firstArg = $call->args[0]->value;

                    if (! $firstArg instanceof Expr\Array_) {
                        continue;
                    }

                    $requestKeys = [];

                    foreach ($firstArg->items as $item) {
                        if ($item === null || $item->value === null) {
                            continue;
                        }

                        if ($this->isRequestMethodCall($item->value, $allRequestNames)) {
                            $requestKeys[] = $item->key instanceof Node\Scalar\String_
                                ? $item->key->value
                                : ($item->key instanceof Node\Identifier ? $item->key->name : '?');
                        }
                    }

                    if (empty($requestKeys)) {
                        continue;
                    }

                    $className = $call->class instanceof Node\Name
                        ? $call->class->toString()
                        : '?';

                    $line = $call->getStartLine();

                    $matches[] = new MatchResult(
                        name: $className,
                        pattern: '',
                        match: $className . '::from()',
                        line: $line,
                        offset: null,
                        content: $this->getSnippet($input->content, $line),
                        groups: $requestKeys,
                    );
                }
            }
        }

        return $input->with(matches: $matches);
    }

    /**
     * Check if the expression is a method call on a request variable.
     */
    private function isRequestMethodCall(Expr $expr, array $requestNames): bool
    {
        if (! $expr instanceof Expr\MethodCall) {
            return false;
        }

        return $this->isRequestObject($expr->var, $requestNames);
    }

    /**
     * Check if the expression is a request variable, $this->request property, or request() helper.
     */
    private function isRequestObject(Expr $var, array $requestNames): bool
    {
        // $request->method()
        if ($var instanceof Expr\Variable && is_string($var->name)) {
            return in_array($var->name, $requestNames, true);
        }

        // $this->request->method()
        if ($var instanceof Expr\PropertyFetch
            && $var->var instanceof Expr\Variable
            && $var->var->name === 'this'
            && $var->name instanceof Node\Identifier
        ) {
            return in_array($var->name->toString(), $requestNames, true);
        }

        // request()->method()
        if ($var instanceof Expr\FuncCall
            && $var->name instanceof Node\Name
            && $var->name->toString() === 'request'
        ) {
            return true;
        }

        return false;
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

            if (TypeChecker::isRequestType($fqcn)) {
                $names[] = $param->var->name;
            }
        }

        return $names;
    }

    /**
     * Resolve class-level properties and promoted constructor parameters that are request types.
     *
     * @return array<string>
     */
    private function resolvePropertyRequestNames(Stmt\Class_ $class, array $useStatements, ?string $namespace): array
    {
        $names = [];

        foreach ($class->getProperties() as $property) {
            if ($property->type === null) {
                continue;
            }

            $typeName = $this->getTypeName($property->type);

            if ($typeName === null) {
                continue;
            }

            $fqcn = $this->resolveFullyQualifiedName($typeName, $useStatements, $namespace);

            if (TypeChecker::isRequestType($fqcn)) {
                foreach ($property->props as $prop) {
                    $names[] = $prop->name->toString();
                }
            }
        }

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

                if (TypeChecker::isRequestType($fqcn)) {
                    $names[] = $param->var->name;
                }
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
