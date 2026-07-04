<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

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
 * The set of classes that TRAVEL BACK in an HTTP response — the ones a page actually sends to the
 * frontend. There is more than one way an object leaves the backend, so this looks at every response
 * boundary, not just Inertia:
 *
 *  1. a controller RETURNS it — the returned expression of a public method on a class that extends the
 *     framework `Controller`, whether the object is returned directly (`return EditorShell::for($id)`,
 *     a Spatie `Data` is `Responsable`) or nested in the returned array (`return ['shell' => …]`), plus
 *     the method's declared return type; and
 *  2. it is handed to a renderer — an argument of `Inertia::render(...)` or the `inertia(...)` helper.
 *
 * A boundary expression is resolved to a class two ways, so indirection doesn't hide a page object: the
 * INLINE construction (`X::from(...)` / `X::for(...)` / `new X`, at any depth) is read straight off the
 * AST, and every returned/argument expression (and each value of a returned/passed array) is also typed
 * through the {@see TypeResolver} provenance engine — so `$page = EditorShell::for($id); return $page;`
 * still resolves to `EditorShell`. (The call graph's `callersOf` and the field-nil `ValueFlow` answer
 * different questions — caller-by-receiver and forward null-provenance — so neither fits "reaches a
 * response"; expression typing does.)
 *
 * This is the load-bearing half of the page-object identity: a big composed `Data` that never reaches
 * a response is an internal aggregate, not a page object. Built ONCE per codebase (an AST walk, like
 * {@see DataClassShape}) and memoised by the codebase object via a {@see \WeakMap}. Records every
 * resolved class FQCN regardless of framework meaning — the caller ({@see PageObject}) decides which
 * of them are `Data`.
 */
final class ResponseSurface
{
    private static ?\WeakMap $memo = null;

    /**
     * @param  array<string, true>  $bound  FQCN => true for every class that reaches a response boundary
     */
    private function __construct(private readonly array $bound) {}

    public static function forCodebase(Codebase $codebase): self
    {
        self::$memo ??= new \WeakMap();

        return self::$memo[$codebase] ??= self::build($codebase);
    }

    /**
     * Does an instance of this class travel back in a response anywhere in the codebase?
     */
    public function isResponseBound(?string $fqcn): bool
    {
        return $fqcn !== null && isset($this->bound[ltrim($fqcn, '\\')]);
    }

    private static function build(Codebase $codebase): self
    {
        $bound = [];
        $finder = new NodeFinder;
        $types = TypeResolver::forCodebase($codebase);

        foreach ($codebase->files() as $file) {
            foreach ($finder->find($file->ast, self::isRenderer(...)) as $render) {
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
        for ($current = $node->getAttribute('parent'); $current instanceof Node; $current = $current->getAttribute('parent')) {
            if ($current instanceof FunctionLike) {
                return $current;
            }
        }

        return null;
    }

    private static function enclosingClassName(Node $node): ?string
    {
        for ($current = $node->getAttribute('parent'); $current instanceof Node; $current = $current->getAttribute('parent')) {
            if ($current instanceof ClassLike) {
                return ($current->namespacedName ?? null)?->toString();
            }
        }

        return null;
    }

    /**
     * Is this node a renderer that ships props to the frontend — `Inertia::render(...)` or `inertia(...)`?
     */
    private static function isRenderer(Node $node): bool
    {
        if ($node instanceof StaticCall) {
            return $node->class instanceof Name
                && ltrim($node->class->toString(), '\\') === LaravelNode::INERTIA
                && $node->name instanceof Identifier
                && $node->name->toString() === 'render';
        }

        return $node instanceof FuncCall
            && $node->name instanceof Name
            && ltrim($node->name->toString(), '\\') === LaravelNode::INERTIA_HELPER;
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
        /** @var StaticCall|New_ $node */
        return ltrim($node->class->toString(), '\\');
    }
}
