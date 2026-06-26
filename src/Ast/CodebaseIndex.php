<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeFinder;

/**
 * The call graph: who calls what, and the types that flow into a call's receiver.
 * Built once per {@see Codebase} and used by detectors that must reason across
 * files — e.g. "this `?T` finder is de-nulled by all its callers".
 *
 * Receiver resolution is deliberately conservative: `$this`, a typed parameter,
 * and `$this->typedProperty`. An unresolved receiver is simply not a match —
 * the graph never guesses.
 */
final class CodebaseIndex
{
    /** @var array<string, array<string, string>>|null  FQCN => [property => class FQCN] */
    private ?array $propertyTypes = null;

    public function __construct(private readonly Codebase $codebase) {}

    /**
     * Every method whose return type is a nullable class (`?C` / `C | null`) — a
     * finder that resolves to a value-or-null.
     *
     * @return list<NodeMatch>
     */
    public function nullableObjectFinders(): array
    {
        $finders = [];
        $finder = new NodeFinder;

        foreach ($this->codebase->files() as $file) {
            foreach ($finder->findInstanceOf($file->ast, ClassMethod::class) as $method) {
                /** @var ClassMethod $method */
                if (TypeName::nullableClass($method->returnType) !== null) {
                    $finders[] = new NodeMatch($method, $file);
                }
            }
        }

        return $finders;
    }

    /**
     * Every call site `->$method(...)` whose receiver resolves to $fqcn (or a
     * subclass of it).
     *
     * @return list<NodeMatch>
     */
    public function callersOf(string $fqcn, string $method): array
    {
        $callers = [];
        $finder = new NodeFinder;

        foreach ($this->codebase->files() as $file) {
            $calls = $finder->find($file->ast, static fn (Node $node): bool =>
                ($node instanceof MethodCall || $node instanceof NullsafeMethodCall)
                && $node->name instanceof Identifier
                && $node->name->toString() === $method);

            foreach ($calls as $call) {
                /** @var MethodCall|NullsafeMethodCall $call */
                $receiver = $this->resolveReceiverType($call);

                if ($receiver !== null && ($receiver === $fqcn || $this->codebase->extends($receiver, $fqcn))) {
                    $callers[] = new NodeMatch($call, $file);
                }
            }
        }

        return $callers;
    }

    /**
     * The class FQCN a call's receiver resolves to, or null when it can't be
     * pinned down from `$this`, a typed param, or `$this->typedProperty`.
     */
    private function resolveReceiverType(MethodCall|NullsafeMethodCall $call): ?string
    {
        $receiver = $call->var;
        $scope = new AstNode($call);

        if ($receiver instanceof Variable && is_string($receiver->name)) {
            if ($receiver->name === 'this') {
                return $scope->enclosingClassName();
            }

            return $this->paramType($scope->enclosingFunction(), $receiver->name);
        }

        if ($receiver instanceof PropertyFetch
            && $receiver->var instanceof Variable
            && $receiver->var->name === 'this'
            && $receiver->name instanceof Identifier
        ) {
            $class = $scope->enclosingClassName();

            return $class === null ? null : ($this->propertyTypes()[$class][$receiver->name->toString()] ?? null);
        }

        return null;
    }

    private function paramType(?Node $function, string $name): ?string
    {
        if (! $function instanceof Node\FunctionLike) {
            return null;
        }

        foreach ($function->getParams() as $param) {
            if ($param->var instanceof Variable && $param->var->name === $name) {
                return TypeName::class($param->type);
            }
        }

        return null;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function propertyTypes(): array
    {
        if ($this->propertyTypes !== null) {
            return $this->propertyTypes;
        }

        $map = [];

        foreach ($this->codebase->files() as $file) {
            foreach ((new NodeFinder)->findInstanceOf($file->ast, Node\Stmt\Class_::class) as $class) {
                /** @var Node\Stmt\Class_ $class */
                $fqcn = ($class->namespacedName ?? null)?->toString();

                if ($fqcn === null) {
                    continue;
                }

                $map[$fqcn] = $this->propertiesOf($class);
            }
        }

        return $this->propertyTypes = $map;
    }

    /**
     * @return array<string, string>  property name => class FQCN
     */
    private function propertiesOf(Node\Stmt\Class_ $class): array
    {
        $types = [];

        foreach ($class->getProperties() as $property) {
            /** @var Property $property */
            $type = TypeName::class($property->type);

            foreach ($property->props as $prop) {
                if ($type !== null) {
                    $types[$prop->name->toString()] = $type;
                }
            }
        }

        foreach ($class->getMethod('__construct')?->getParams() ?? [] as $param) {
            /** @var Param $param */
            if ($param->flags !== 0 && $param->var instanceof Variable && is_string($param->var->name)) {
                $type = TypeName::class($param->type);

                if ($type !== null) {
                    $types[$param->var->name] = $type;
                }
            }
        }

        return $types;
    }
}
