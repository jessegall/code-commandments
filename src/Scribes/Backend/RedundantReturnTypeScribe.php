<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Cli\Scope\Scope;
use JesseGall\CodeCommandments\Scribes\Edit;
use JesseGall\CodeCommandments\Scribes\Scribe;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;

/**
 * Strips a redundant explicit return type from a single-expression arrow function
 * when the expression PROVABLY yields exactly that class. It's noise the one-liner
 * already makes obvious:
 *
 *   static fn (): Foo => Foo::make($x)   →   static fn () => Foo::make($x)
 *
 * Deliberately conservative, because a return type is not always redundant:
 *  - only an OBJECT type (a plain class `Name`) — scalar return types coerce at
 *    runtime (`fn (): int => "5"` returns int), so they are never touched; nullable,
 *    union, and `self`/`static`/`parent` types are left alone;
 *  - only when the expression yields EXACTLY that class — `new Foo(...)` of the same
 *    class, or `Foo::method(...)` whose method's declared return resolves to
 *    `self`/`static`/`Foo` (looked up in the codebase). A widening type (declaring a
 *    parent/interface) is kept; an unresolvable call (vendor, dynamic) is skipped.
 */
final class RedundantReturnTypeScribe extends Scribe
{
    public function rewrites(Codebase $codebase, Scope $scope): array
    {
        $classes = $this->classNodes($codebase);
        $finder = new NodeFinder;

        /** @var array<string, list<Edit>> $editsByFile */
        $editsByFile = [];

        foreach ($codebase->files() as $file) {
            if (! $scope->includes($file->path)) {
                continue;
            }

            $source = $file->source;

            foreach ($finder->findInstanceOf($file->ast, ArrowFunction::class) as $arrow) {
                if ($this->isRedundant($arrow->returnType, $arrow->expr, $classes)) {
                    $editsByFile[$file->path][] = $this->removeReturnType($arrow, $source);
                }
            }
        }

        $changed = [];

        foreach ($editsByFile as $file => $edits) {
            $source = $codebase->sourceOf($file) ?? '';
            $new = $this->applyEdits($source, $edits);

            if ($new !== $source) {
                $changed[$file] = $new;
            }
        }

        return $changed;
    }

    private function isRedundant(?Node $returnType, ?Node $expr, array $classes): bool
    {
        if (! $returnType instanceof Name || in_array($returnType->toLowerString(), ['self', 'static', 'parent'], true)) {
            return false;
        }

        return $this->yields($expr, ltrim($returnType->toString(), '\\'), $classes);
    }

    /**
     * Does $expr provably evaluate to exactly the class $declared (a resolved FQCN)?
     *
     * @param  array<string, Class_>  $classes
     */
    private function yields(?Node $expr, string $declared, array $classes): bool
    {
        if ($expr instanceof New_ && $expr->class instanceof Name) {
            return ltrim($expr->class->toString(), '\\') === $declared;
        }

        if ($expr instanceof StaticCall && $expr->class instanceof Name && $expr->name instanceof Identifier) {
            $callClass = ltrim($expr->class->toString(), '\\');
            $return = $this->methodReturn($classes, $callClass, $expr->name->toString());

            if ($return === null) {
                return false;
            }

            $resolved = in_array(strtolower($return), ['self', 'static'], true) ? $callClass : ltrim($return, '\\');

            return $resolved === $declared;
        }

        return false;
    }

    /**
     * The declared return type of `$class::$method` as a single class/self/static
     * name, or null when the class/method isn't in the codebase or the return type
     * isn't a single name (nullable/union/scalar — not safely comparable).
     *
     * @param  array<string, Class_>  $classes
     */
    private function methodReturn(array $classes, string $class, string $method): ?string
    {
        $node = ($classes[$class] ?? null)?->getMethod($method);
        $type = $node?->returnType;

        return $type instanceof Name ? $type->toString() : null;
    }

    /**
     * Remove `: ReturnType` from an arrow function: from the `:` (found just before
     * the type) through the type's end.
     */
    private function removeReturnType(ArrowFunction $arrow, string $source): Edit
    {
        $typeStart = $arrow->returnType->getStartFilePos();
        $colon = strrpos(substr($source, 0, $typeStart), ':');

        return new Edit($colon === false ? $typeStart : $colon, $arrow->returnType->getEndFilePos(), '');
    }

    /**
     * @return array<string, Class_>  FQCN => class node
     */
    private function classNodes(Codebase $codebase): array
    {
        $map = [];
        $finder = new NodeFinder;

        foreach ($codebase->files() as $file) {
            foreach ($finder->findInstanceOf($file->ast, Class_::class) as $class) {
                if (($class->namespacedName ?? null) !== null) {
                    $map[$class->namespacedName->toString()] = $class;
                }
            }
        }

        return $map;
    }
}
