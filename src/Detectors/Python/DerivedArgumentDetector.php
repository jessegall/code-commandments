<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\Expr\ExprKind;
use JesseGall\CodeCommandments\Py\ExprMatch;
use JesseGall\CodeCommandments\Py\ModuleFile;
use JesseGall\CodeCommandments\Py\Node\ClassDef;
use JesseGall\CodeCommandments\Py\Node\FunctionDef;
use JesseGall\CodeCommandments\Py\Node\Param;
use JesseGall\CodeCommandments\Py\Type;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\DerivedArgument;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\WholeTree;

/**
 * A call that hands over a projection of a value beside the value itself, or the value in pieces — the
 * twin of the backend's {@see \JesseGall\CodeCommandments\Detectors\Backend\DerivedArgumentDetector}. The
 * evidence is one name reached twice in one call, so the function provably holds what the derivation needs;
 * and it is a question about the PARAMETER, so every call filling it must do the same.
 */
final class DerivedArgumentDetector implements Detector, WholeTree
{
    /**
     * How many pieces of one value, with the value itself nowhere in the call, say the function is
     * reassembling it. Two is a generic helper filling a title and a subtitle; three is an object in pieces.
     */
    private const int REASSEMBLED = 3;

    public function sin(): Sin
    {
        return new DerivedArgument();
    }

    public function find(Codebase $codebase): array
    {
        $index = $codebase->index();
        $redundant = [];
        $supplied = [];
        $unresolved = [];

        foreach ($codebase->whereCall()->get() as $call) {
            $target = $index->targetOf($call->expr);
            $bound = $index->argumentsAt($call->expr);

            if ($target->isNone() || $bound->isNone()) {
                $unresolved[$this->memberCalled($call->expr)] = true;

                continue;
            }

            if ($this->buildsItsOwnClass($call, $target->unwrap())) {
                continue;
            }

            $slot = $index->declarationOf($target->unwrap());

            foreach (array_keys($bound->unwrap()) as $name) {
                $supplied["{$slot}#{$name}"] = ($supplied["{$slot}#{$name}"] ?? 0) + 1;
            }

            foreach ($this->redundantParameters($call, $target->unwrap(), $bound->unwrap(), $codebase) as $name) {
                $redundant["{$slot}#{$name}"][$target->unwrap()->name][] = $call;
            }
        }

        $findings = [];

        foreach ($redundant as $slot => $byName) {
            foreach ($byName as $name => $calls) {
                if (count($calls) === $supplied[$slot] && ! isset($unresolved[$name])) {
                    foreach ($calls as $call) {
                        $findings[spl_object_id($call)] = $call;
                    }
                }
            }
        }

        return array_values($findings);
    }

    /**
     * The parameters this call fills with a projection of a name it reaches twice — beside the name itself,
     * or as one of {@see REASSEMBLED} pieces of it.
     *
     * @param  array<string, Expr>  $bound
     * @return list<string>
     */
    private function redundantParameters(ExprMatch $call, FunctionDef $target, array $bound, Codebase $codebase): array
    {
        $params = array_column(array_map(static fn (Param $param) => [$param->name, $param], $target->params), 1, 0);

        if (array_any(array_keys($bound), static fn (string $name): bool => ($params[$name] ?? null)?->takesAnything() === true)) {
            return []; // a slot told nothing about what it holds cannot derive anything from it
        }

        $receiver = $call->module->receiverNameAt($call->expr)->unwrapOr('');
        $whole = [];
        $pieces = [];

        foreach ($bound as $name => $argument) {
            if ($argument->is(ExprKind::Name) && $argument->get('name') !== $receiver) {
                $whole[(string) $argument->get('name')] = true;

                continue;
            }

            $root = $argument->projectionRoot();

            if ($root !== '' && $root !== $receiver && ($params[$name] ?? null)?->isScalar() === true) {
                $pieces[$root][$name] = $argument;
            }
        }

        $redundant = array_filter($pieces, fn (array $arguments, string $root): bool => isset($whole[$root])
            || (count($arguments) >= self::REASSEMBLED && $this->couldTakeWhole($call, $target, reset($arguments), $root, $codebase)), ARRAY_FILTER_USE_BOTH);

        return array_merge([], ...array_map(array_keys(...), array_values($redundant)));
    }

    /**
     * Could $target take whole the value $root names? Only when mypy knows its class — the fix names a type,
     * and untyped there is none to name — and only when importing that class opens no cycle: a package
     * that already imports $target's is one it cannot reach back into, and a mapper between two layers is
     * the one place allowed to see both, so taking the pieces is its whole job.
     */
    private function couldTakeWhole(ExprMatch $call, FunctionDef $target, Expr $piece, string $root, Codebase $codebase): bool
    {
        $named = array_values(array_filter($piece->flatten(), static fn (Expr $part): bool => $part->is(ExprKind::Name) && $part->get('name') === $root))[0];

        return $codebase->types()->at($call->module->file, $named->start, $named->end)
            ->andThen(static fn (Type $type) => $type->className())
            ->andThen(static fn (string $class) => $codebase->moduleCalled(substr($class, 0, (int) strrpos($class, '.'))))
            ->isSomeAnd(static fn (ModuleFile $subject): bool => ! $codebase->packageGraph()->wouldCloseACycle($codebase->index()->moduleOf($target), $subject));
    }

    /**
     * Is this call a class building itself — `Summary(...)` inside `Summary` — where the rule sends a
     * derivation, not where it finds one?
     */
    private function buildsItsOwnClass(ExprMatch $call, FunctionDef $target): bool
    {
        return $target->name === '__init__' && $call->module->classOf($call->expr)->isSomeAnd(
            static fn (ClassDef $own): bool => $own->initializer()->isSomeAnd(static fn (FunctionDef $init): bool => $init === $target),
        );
    }

    /**
     * The method a call sends by name — `persist` in `repo.persist(…)` — empty for a call of anything else.
     * One send the index cannot resolve could be filling any method of that name.
     */
    private function memberCalled(Expr $call): string
    {
        $callee = $call->get('callee');

        return $callee->is(ExprKind::Attribute) ? (string) $callee->get('name') : '';
    }
}
