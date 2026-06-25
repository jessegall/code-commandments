<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Php;

use JesseGall\CodeCommandments\Support\ExtractsLineSnippet;
use JesseGall\CodeCommandments\Support\Pipes\MatchResult;
use JesseGall\CodeCommandments\Support\Pipes\Pipe;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;
use JesseGall\PhpTypes\T_String;

/**
 * Find the SAME field hydrated to the same type more than once via a static
 * `::from()` call — e.g. `StepExtrasData::from($step->extras)` appearing in
 * several methods. The repetition is the signal: a field that keeps being
 * hydrated should simply BE that type (a typed Data property,
 * `#[DataCollectionOf]`, or a Cast), hydrated once at the boundary.
 *
 * Detection is intentionally narrow to stay low-false-positive:
 *   - only static `<Class>::from(<arg>)` calls (configurable method names),
 *   - only when <arg> is a property fetch (`$x->prop`) — the thing that can
 *     be turned into a typed field,
 *   - grouped by (target class short name, property name), ignoring the base
 *     variable so the same field reached via different locals still groups,
 *   - flagged when a group occurs >= `min_occurrences` times.
 *
 * @implements Pipe<PhpContext, PhpContext>
 */
final class FindRepeatedHydration implements Pipe
{
    use ExtractsLineSnippet;

    /** @var list<string> */
    private array $methods = ['from'];

    private int $minOccurrences = 2;

    /**
     * @param  list<string>  $methods
     */
    public function withMethods(array $methods): self
    {
        $this->methods = $methods !== [] ? array_values($methods) : ['from'];

        return $this;
    }

    public function withMinOccurrences(int $min): self
    {
        $this->minOccurrences = max(2, $min);

        return $this;
    }

    public function handle(mixed $input): mixed
    {
        if ($input->ast === null) {
            return $input->with(matches: []);
        }

        $nodeFinder = new NodeFinder;

        /** @var array<Expr\StaticCall> $calls */
        $calls = $nodeFinder->findInstanceOf($input->ast, Expr\StaticCall::class);

        /** @var array<string, array{target: string, property: string, count: int, line: int}> $groups */
        $groups = [];

        foreach ($calls as $call) {
            $hydration = $this->hydrationFrom($call);

            if ($hydration === null) {
                continue;
            }

            $key = $hydration['target'] . '::' . $hydration['property'];

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'target' => $hydration['target'],
                    'property' => $hydration['property'],
                    'count' => 0,
                    'line' => $call->getStartLine(),
                ];
            }

            $groups[$key]['count']++;
        }

        $matches = [];

        foreach ($groups as $group) {
            if ($group['count'] < $this->minOccurrences) {
                continue;
            }

            $matches[] = new MatchResult(
                name: $group['property'],
                pattern: T_String::empty(),
                match: $group['target'] . '::from($…->' . $group['property'] . ')',
                line: $group['line'],
                offset: null,
                content: $this->lineSnippet($input->content, $group['line']),
                groups: [
                    'target' => $group['target'],
                    'property' => $group['property'],
                    'count' => (string) $group['count'],
                ],
            );
        }

        return $input->with(matches: $matches);
    }

    /**
     * Resolve a `<Class>::from($x->prop)` call into its (target, property),
     * or null when the call isn't a property-fetch hydration we can advise on.
     *
     * @return array{target: string, property: string}|null
     */
    private function hydrationFrom(Expr\StaticCall $call): ?array
    {
        if (! $call->name instanceof Node\Identifier) {
            return null;
        }

        if (! in_array($call->name->toString(), $this->methods, true)) {
            return null;
        }

        if (! $call->class instanceof Node\Name) {
            return null;
        }

        $target = $call->class->getLast();

        if (in_array($target, ['self', 'static', 'parent'], true)) {
            return null;
        }

        if (count($call->args) !== 1 || ! $call->args[0] instanceof Node\Arg) {
            return null;
        }

        $arg = $call->args[0]->value;

        if (($arg instanceof Expr\PropertyFetch || $arg instanceof Expr\NullsafePropertyFetch)
            && $arg->name instanceof Node\Identifier
        ) {
            return ['target' => $target, 'property' => $arg->name->toString()];
        }

        return null;
    }

}
