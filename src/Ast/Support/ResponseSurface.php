<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Laravel\LaravelNode;
use JesseGall\CodeCommandments\Ast\TypeName;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
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
 * This is the load-bearing half of the page-object identity: a big composed `Data` that never reaches
 * a response is an internal aggregate, not a page object. Built ONCE per codebase (an AST walk, like
 * {@see DataClassShape}) and memoised by the codebase object via a {@see \WeakMap}. Records every
 * constructed class FQCN regardless of framework meaning — the caller ({@see PageObject}) decides which
 * of them are `Data`.
 */
final class ResponseSurface
{
    private static ?\WeakMap $memo = null;

    /**
     * @param  array<string, true>  $bound  FQCN => true for every class constructed at a response boundary
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

        foreach ($codebase->files() as $file) {
            foreach ($finder->find($file->ast, self::isRenderer(...)) as $render) {
                self::collectConstructions($render->getArgs(), $finder, $bound);
            }

            foreach ($finder->findInstanceOf($file->ast, Class_::class) as $class) {
                if (! $codebase->extends(($class->namespacedName ?? null)?->toString(), LaravelNode::CONTROLLER)) {
                    continue;
                }

                self::collectControllerReturns($class, $finder, $bound);
            }
        }

        return new self($bound);
    }

    /**
     * Every class a controller's public actions hand back — the declared return-type class and every
     * construction inside a returned expression (the object itself, or objects nested in a returned array).
     *
     * @param  array<string, true>  $bound
     */
    private static function collectControllerReturns(Class_ $controller, NodeFinder $finder, array &$bound): void
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
                    self::collectConstructions([$return->expr], $finder, $bound);
                }
            }
        }
    }

    /**
     * Record the class named by every construction (`X::from(...)` / `X::for(...)` / `new X`) found
     * within the given nodes — a page object is built through a factory or `new` where it is sent back.
     *
     * @param  array<Node>  $nodes
     * @param  array<string, true>  $bound
     */
    private static function collectConstructions(array $nodes, NodeFinder $finder, array &$bound): void
    {
        foreach ($finder->find($nodes, self::isConstruction(...)) as $construction) {
            $bound[self::constructedClass($construction)] = true;
        }
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
