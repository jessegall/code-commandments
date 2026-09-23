<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py;

use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\Expr\ExprKind;
use JesseGall\CodeCommandments\Py\Node\ClassDef;
use JesseGall\CodeCommandments\Py\Node\ForLoop;
use JesseGall\CodeCommandments\Py\Node\FunctionDef;
use JesseGall\CodeCommandments\Py\Node\Node;
use JesseGall\PhpTypes\Option;

/**
 * Exiled behaviour — a method that reaches through one other object it was handed, looping its collection
 * or writing its fields, more than it touches its own state: work that belongs on that object. The Python
 * twin of the backend's {@see \JesseGall\CodeCommandments\Ast\Support\FeatureEnvy}, on the same signals
 * and no names: a class filling in its base's contract is a polymorphic component, a method that builds a
 * class is a mapper, and a loop that hands each element to one of the method's own collaborators is
 * orchestration.
 */
final readonly class FeatureEnvy
{
    public function __construct(private Codebase $codebase) {}

    /**
     * The parameter $method, written in $module, envies — none when it envies nothing.
     *
     * @return Option<string>
     */
    public function enviedParameter(FunctionDef $method, ModuleFile $module): Option
    {
        $class = $module->boundClassOf($method);

        if ($class->isNoneOr(fn (ClassDef $host) => $this->fillsAContract($host, $module)) || $this->constructs($method, $module)) {
            return Option::none();
        }

        $self = $method->params[0]->name;
        $owned = $this->codebase->ownedParameters($method, $class->unwrap());
        $reaches = $method->attributeReachesOn([$self, ...$owned]);
        $own = $reaches[$self] ?? 0;
        unset($reaches[$self]);

        if (count($reaches) !== 1 || reset($reaches) <= $own) {
            return Option::none(); // two envied objects is orchestration; no more reaches than its own is not envy
        }

        $envied = (string) array_key_first($reaches);
        $mutates = $this->mutates($method, $envied);
        $iterates = $this->iterates($method, $module, $envied);

        if ($iterates && ! $mutates && $this->delegatesElements($method->loopsOver($envied), $self, $method->name)) {
            return Option::none();
        }

        return $iterates || $mutates ? Option::some($envied) : Option::none();
    }

    /**
     * Does $class fill in a contract its base declares — override one of its methods, dunders aside? Then it
     * is a polymorphic component (a strategy, a handler, a plugin part) whose behaviour is meant to act on
     * other types — the backend's interface exemption, said the way Python says it.
     */
    private function fillsAContract(ClassDef $class, ModuleFile $module): bool
    {
        return array_any($class->body->body, fn (Node $member): bool => $member instanceof FunctionDef
            && ! $member->isDunder()
            && $this->codebase->index()->isOverride($member, $module));
    }

    /**
     * Does $method build a class it names — a mapper or a factory, whose job is to read another object?
     */
    private function constructs(FunctionDef $method, ModuleFile $module): bool
    {
        return array_any($module->expressionsIn($method), fn (Expr $expression): bool => $expression->isCall()
            && $expression->get('callee')->dottedName() !== ''
            && $this->codebase->declaresClass($expression->get('callee')->dottedName()));
    }

    /**
     * Does $method loop over a collection of $name — `for line in order.lines`, or a comprehension over it?
     */
    private function iterates(FunctionDef $method, ModuleFile $module, string $name): bool
    {
        return $method->loopsOver($name) !== [] || array_any($module->expressionsIn($method), static fn (Expr $expression): bool => $expression->is(ExprKind::ComprehensionFor)
            && $expression->get('iterable')->projectionRoot() === $name);
    }

    /**
     * Does $method write one of $name's attributes — `order.status = …`, `order.total += …`?
     */
    private function mutates(FunctionDef $method, string $name): bool
    {
        return array_any($method->body->descendants(), static fn (Node $node): bool => array_any(
            $node->writtenTargets(),
            static fn (Expr $target): bool => $target->is(ExprKind::Attribute) && $target->rootName() === $name,
        ));
    }

    /**
     * Does each of $loops hand its element to one of $self's own collaborators — `self.printer.print(line)` —
     * the orchestrator doing its own job? Calling $method, the one the loops are in, again is recursion,
     * which belongs where the collection is.
     *
     * @param  list<ForLoop>  $loops
     */
    private function delegatesElements(array $loops, string $self, string $method): bool
    {
        return $loops !== [] && array_all($loops, static fn (ForLoop $loop): bool => array_any(
            $loop->body->expressionsWithin(),
            static fn (Expr $call): bool => $call->isCall()
                && $call->get('callee')->is(ExprKind::Attribute)
                && $call->get('callee')->rootName() === $self
                && $call->get('callee')->dottedName() !== "{$self}.{$method}"
                && array_any($call->get('arguments'), static fn (Expr $argument): bool => $argument->dottedName() === $loop->target->dottedName()),
        ));
    }
}
