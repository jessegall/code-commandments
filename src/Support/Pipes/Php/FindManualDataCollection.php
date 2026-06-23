<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Php;

use JesseGall\CodeCommandments\Support\Pipes\MatchResult;
use JesseGall\CodeCommandments\Support\Pipes\Pipe;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use PhpParser\NodeFinder;
use JesseGall\PhpTypes\T_String;

/**
 * Find a collection of Data objects being hydrated by hand — a `foreach`
 * that appends `SomeData::from($row)` to an array, or an `array_map` whose
 * callback is `SomeData::from`. Spatie Data hydrates a whole collection
 * declaratively via `#[DataCollectionOf(SomeData::class)]` / `::collect()`,
 * so the loop is reimplementing the framework one element at a time.
 *
 * Only a straight `::from` (configurable) counts — a custom factory like
 * `::fromInputPort($p)` inside an array_map is genuine mapping and is left
 * alone.
 *
 * @implements Pipe<PhpContext, PhpContext>
 */
final class FindManualDataCollection implements Pipe
{
    use ExtractsLineSnippet;

    /** @var list<string> */
    private array $methods = ['from'];

    /**
     * @param  list<string>  $methods
     */
    public function withMethods(array $methods): self
    {
        $this->methods = $methods !== [] ? array_values($methods) : ['from'];

        return $this;
    }

    public function handle(mixed $input): mixed
    {
        if ($input->ast === null) {
            return $input->with(matches: []);
        }

        $nodeFinder = new NodeFinder;
        $matches = [];

        /** @var array<Node\Stmt\Foreach_> $loops */
        $loops = $nodeFinder->findInstanceOf($input->ast, Node\Stmt\Foreach_::class);

        foreach ($loops as $loop) {
            $target = $this->loopHydrationTarget($loop);

            if ($target !== null) {
                $matches[] = $this->match($input->content, $loop->getStartLine(), $target, 'loop');
            }
        }

        /** @var array<Expr\FuncCall> $calls */
        $calls = $nodeFinder->findInstanceOf($input->ast, Expr\FuncCall::class);

        foreach ($calls as $call) {
            if (! $call->name instanceof Node\Name || $call->name->toString() !== 'array_map') {
                continue;
            }

            $target = $this->arrayMapHydrationTarget($call);

            if ($target !== null) {
                $matches[] = $this->match($input->content, $call->getStartLine(), $target, 'array_map');
            }
        }

        return $input->with(matches: $matches);
    }

    private function match(string $content, int $line, string $target, string $form): MatchResult
    {
        return new MatchResult(
            name: $target,
            pattern: T_String::empty(),
            match: $target . '::from() in a ' . $form,
            line: $line,
            offset: null,
            content: $this->lineSnippet($content, $line),
            groups: [
                'target' => $target,
                'form' => $form,
            ],
        );
    }

    /**
     * The Data target if this foreach just appends `<Class>::from($value)`
     * (the loop's own value variable) to an array; null otherwise.
     */
    private function loopHydrationTarget(Node\Stmt\Foreach_ $loop): ?string
    {
        if (! $loop->valueVar instanceof Expr\Variable || ! is_string($loop->valueVar->name)) {
            return null;
        }

        $valueVar = $loop->valueVar->name;
        $nodeFinder = new NodeFinder;

        /** @var array<Expr\Assign> $assigns */
        $assigns = $nodeFinder->findInstanceOf($loop->stmts, Expr\Assign::class);

        foreach ($assigns as $assign) {
            // Append form: $acc[] = ...
            if (! $assign->var instanceof Expr\ArrayDimFetch || $assign->var->dim !== null) {
                continue;
            }

            $target = $this->fromTarget($assign->expr);

            if ($target !== null && $this->referencesVariable($assign->expr, $valueVar)) {
                return $target;
            }
        }

        return null;
    }

    /**
     * The Data target if array_map's callback is `<Class>::from`.
     */
    private function arrayMapHydrationTarget(Expr\FuncCall $call): ?string
    {
        if ($call->args === [] || ! $call->args[0] instanceof Node\Arg) {
            return null;
        }

        $callback = $call->args[0]->value;

        // fn ($x) => Class::from($x)
        if ($callback instanceof Expr\ArrowFunction) {
            return $this->fromTarget($callback->expr);
        }

        // function ($x) { return Class::from($x); }
        if ($callback instanceof Expr\Closure) {
            $nodeFinder = new NodeFinder;

            foreach ($nodeFinder->findInstanceOf($callback->stmts, Expr\StaticCall::class) as $static) {
                $target = $this->fromTarget($static);

                if ($target !== null) {
                    return $target;
                }
            }

            return null;
        }

        // [Class::class, 'from']
        if ($callback instanceof Expr\Array_) {
            return $this->arrayCallableTarget($callback);
        }

        // Class::from(...)  (first-class callable)
        if ($callback instanceof Expr\StaticCall) {
            return $this->fromTarget($callback);
        }

        return null;
    }

    private function arrayCallableTarget(Expr\Array_ $array): ?string
    {
        if (count($array->items) !== 2) {
            return null;
        }

        [$classItem, $methodItem] = $array->items;

        if ($classItem === null || $methodItem === null) {
            return null;
        }

        if (! $methodItem->value instanceof Scalar\String_
            || ! in_array($methodItem->value->value, $this->methods, true)
        ) {
            return null;
        }

        if ($classItem->value instanceof Expr\ClassConstFetch
            && $classItem->value->class instanceof Node\Name
        ) {
            return $this->shortName($classItem->value->class->getLast());
        }

        return null;
    }

    /**
     * The short class name if the expression is `<Class>::from(...)` with a
     * configured method name and a real (non self/static/parent) class.
     */
    private function fromTarget(?Node $expr): ?string
    {
        if (! $expr instanceof Expr\StaticCall) {
            return null;
        }

        if (! $expr->name instanceof Node\Identifier
            || ! in_array($expr->name->toString(), $this->methods, true)
        ) {
            return null;
        }

        if (! $expr->class instanceof Node\Name) {
            return null;
        }

        $short = $expr->class->getLast();

        if (in_array($short, ['self', 'static', 'parent'], true)) {
            return null;
        }

        return $short;
    }

    private function referencesVariable(Node $subtree, string $name): bool
    {
        $nodeFinder = new NodeFinder;

        foreach ($nodeFinder->findInstanceOf($subtree, Expr\Variable::class) as $var) {
            if (is_string($var->name) && $var->name === $name) {
                return true;
            }
        }

        return false;
    }

    private function shortName(string $name): string
    {
        $parts = explode('\\', $name);

        return end($parts) ?: $name;
    }

}
