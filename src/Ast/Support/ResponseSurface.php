<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Laravel\LaravelNode;
use JesseGall\CodeCommandments\Ast\TypeName;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;

/**
 * Identifies classes that reach HTTP response boundaries (controller returns, Inertia::render
 * arguments). Resolves indirection via AST and {@see TypeResolver} so indirect returns like
 * `$page = EditorShell::for($id); return $page;` still resolve. Built once and memoised per codebase.
 */
final class ResponseSurface
{
    use MemoisedPerCodebase;

    /**
     * @param  array<string, true>  $bound  FQCN => true for every class that reaches a response boundary
     */
    private function __construct(private readonly array $bound) {}

    /**
     * Does an instance of this class travel back in a response anywhere in the codebase?
     */
    public function isResponseBound(?string $fqcn): bool
    {
        return $fqcn !== null && isset($this->bound[ltrim($fqcn, '\\')]);
    }

    protected static function build(Codebase $codebase): static
    {
        $bound = [];
        $finder = new NodeFinder;
        $types = TypeResolver::forCodebase($codebase);

        foreach ($codebase->files() as $file) {
            foreach ($finder->find($file->ast, LaravelNode::rendersInertiaPage(...)) as $render) {
                self::collect($render->getArgs(), $codebase, $types, $finder, $bound);
            }

            foreach ($finder->findInstanceOf($file->ast, Class_::class) as $class) {
                if (! $codebase->extends(($class->namespacedName ?? null)?->toString(), LaravelNode::CONTROLLER)) {
                    continue;
                }

                self::collectControllerReturns($class, $codebase, $types, $finder, $bound);
            }
        }

        return new self($bound);
    }

    /**
     * Every class a controller's public actions hand back — the declared return-type class and, for each
     * returned expression, the classes it resolves to.
     *
     * @param  array<string, true>  $bound
     */
    private static function collectControllerReturns(Class_ $controller, Codebase $codebase, TypeResolver $types, NodeFinder $finder, array &$bound): void
    {
        foreach ($controller->getMethods() as $method) {
            if (! $method->isPublic()) {
                continue;
            }

            if (($returnType = TypeName::class($method->returnType)) !== null) {
                $bound[ltrim($returnType, '\\')] = true;
            }

            foreach ($finder->findInstanceOf($method->stmts ?? [], Return_::class) as $return) {
                if ($return->expr !== null) {
                    self::collect([$return->expr], $codebase, $types, $finder, $bound);
                }
            }
        }
    }

    /**
     * Resolve every construction reachable within the given boundary nodes AND the type of every
     * top-level / array-nested expression, recording each class FQCN found.
     *
     * @param  array<Node>  $nodes
     * @param  array<string, true>  $bound
     */
    private static function collect(array $nodes, Codebase $codebase, TypeResolver $types, NodeFinder $finder, array &$bound): void
    {
        foreach ($finder->find($nodes, self::isConstruction(...)) as $construction) {
            $bound[self::constructedClass($construction)] = true;
        }

        foreach ($nodes as $node) {
            foreach (self::candidateExpressions($node) as $expr) {
                $fqcn = self::resolveType($expr, $codebase, $types);

                if ($fqcn !== null) {
                    $bound[$fqcn] = true;
                }
            }
        }
    }

    /**
     * The expressions worth typing at a boundary — the value itself, and (unwrapping a returned/passed
     * array) each of its element values, so `return ['shell' => $shell]` types `$shell`.
     *
     * @return list<Expr>
     */
    private static function candidateExpressions(Node $node): array
    {
        if ($node instanceof Arg) {
            return self::candidateExpressions($node->value);
        }

        if ($node instanceof Array_) {
            $expressions = [];

            foreach ($node->items as $item) {
                $expressions = [...$expressions, ...self::candidateExpressions($item->value)];
            }

            return $expressions;
        }

        return $node instanceof Expr ? [$node] : [];
    }

    /**
     * The class an expression yields — its inline construction if it is one, else its type resolved
     * through provenance (a local assigned from a factory, a method whose return type is a class).
     */
    private static function resolveType(Expr $expr, Codebase $codebase, TypeResolver $types): ?string
    {
        if (self::isConstruction($expr)) {
            return self::constructedClass($expr);
        }

        $function = self::enclosingFunction($expr);

        if ($function === null) {
            return null;
        }

        $resolved = $types->typeOf($expr, $function, self::enclosingClassName($expr));

        return $resolved === null ? null : ltrim($resolved, '\\');
    }

    private static function enclosingFunction(Node $node): ?FunctionLike
    {
        foreach (AstNode::ancestorsOf($node) as $current) {
            if ($current instanceof FunctionLike) {
                return $current;
            }
        }

        return null;
    }

    private static function enclosingClassName(Node $node): ?string
    {
        foreach (AstNode::ancestorsOf($node) as $current) {
            if ($current instanceof ClassLike) {
                return ($current->namespacedName ?? null)?->toString();
            }
        }

        return null;
    }

    /**
     * Is this expression the construction of a named class — `X::from(...)` / `X::for(...)` / `new X`?
     */
    private static function isConstruction(Node $node): bool
    {
        return ($node instanceof StaticCall || $node instanceof New_) && $node->class instanceof Name;
    }

    /**
     * The FQCN a construction expression names — caller guarantees {@see self::isConstruction()}.
     */
    private static function constructedClass(Node $node): string
    {
        /**
         * @var StaticCall|New_ $node
         */
        return ltrim($node->class->toString(), '\\');
    }
}
