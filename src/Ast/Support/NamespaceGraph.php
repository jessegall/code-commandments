<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Support\ClassName;

/**
 * Which namespace depends on which — every reference folded up from classes to the namespaces they
 * live in, built once per codebase. The single home of the dependency graph's shape, read by the
 * rule that forbids a cycle and the command that proposes a layer declaration. An edge runs between
 * INDEPENDENT namespaces the scan declared: a namespace nested in another is part of it, and a
 * vendor target has a direction nothing here can change.
 */
final class NamespaceGraph
{
    use MemoisedPerCodebase;

    /** @var array<string, array<string, list<NodeMatch>>>  from => to => the references that draw that arrow */
    private array $arrows = [];

    private function __construct(Codebase $codebase)
    {
        foreach ($codebase->whereClassReference()->get() as $reference) {
            $from = $reference->namespaceName();
            $target = $reference->referencedClassName();

            if ($from === null || $codebase->declarationMatch($target) === null) {
                continue;
            }

            $to = ClassName::namespace((string) $target);

            if ($to === '' || ClassName::within($to, $from) || ClassName::within($from, $to)) {
                continue;
            }

            $this->arrows[$from][$to][] = $reference;
        }
    }

    /**
     * The graph as `namespace => the namespaces it references`.
     *
     * @return array<string, list<string>>
     */
    public function edges(): array
    {
        return array_map(array_keys(...), $this->arrows);
    }

    /**
     * The arrows to CUT: for every mutual pair of namespaces — each referencing the other — the
     * references of the direction used LESS.
     *
     * A mutual pair is a cycle you can act on. A sprawling many-namespace tangle is a cycle too,
     * but no single arrow in it is the mistake, so naming all of them names nothing; the pair is
     * where a person can actually decide. And between the two directions the thinner one is the
     * accidental one — fewest places to change, and afterwards the pair points one way again.
     *
     * @return list<NodeMatch>
     */
    public function arrowsClosingAMutualPair(): array
    {
        $cutting = [];

        foreach ($this->mutualPairs() as [$from, $to]) {
            array_push($cutting, ...$this->arrows[$from][$to]);
        }

        return $cutting;
    }

    /**
     * Every mutual pair as the DIRECTION worth cutting — `[from, to]`, the thinner of the two. Ties
     * break on the name, so the same codebase always yields the same answer.
     *
     * @return list<array{0: string, 1: string}>
     */
    public function mutualPairs(): array
    {
        $pairs = [];

        foreach ($this->arrows as $from => $targets) {
            foreach ($targets as $to => $references) {
                $back = $this->arrows[$to][$from] ?? null;

                if ($back === null) {
                    continue;
                }

                $weaker = [count($references), $from] <=> [count($back), $to];
                $pairs[$from < $to ? "{$from}\0{$to}" : "{$to}\0{$from}"] = $weaker <= 0 ? [$from, $to] : [$to, $from];
            }
        }

        return array_values($pairs);
    }

    /**
     * The floor the stack rests on: namespaces that reference nothing else of yours, yet that
     * others reference. Both halves matter — the first makes them provably the bottom, the second
     * makes them worth declaring. A namespace nothing depends on is merely isolated, and pinning it
     * down buys no one anything.
     *
     * @return list<string>
     */
    public function foundation(): array
    {
        $depended = [];

        foreach ($this->arrows as $targets) {
            foreach (array_keys($targets) as $target) {
                $depended[$target] = true;
            }
        }

        $floor = array_values(array_filter(
            array_keys($depended),
            fn (string $namespace): bool => ($this->arrows[$namespace] ?? []) === [],
        ));

        sort($floor);

        return $floor;
    }

    /**
     * The namespaces in dependency order — every namespace after the ones it references — paired
     * with the ones no order could place, because they sit in a cycle. What a proposed layer
     * declaration reads off: the first entry may use nothing, the last may use everything before it.
     *
     * @return array{ordered: list<string>, cyclic: list<string>}
     */
    public function dependencyOrder(): array
    {
        $remaining = $this->nodes();
        $ordered = [];

        while ($remaining !== []) {
            $free = array_values(array_filter(
                $remaining,
                fn (string $node): bool => array_all(
                    array_keys($this->arrows[$node] ?? []),
                    static fn (string $target): bool => ! in_array($target, $remaining, true),
                ),
            ));

            if ($free === []) {
                sort($remaining);

                return ['ordered' => $ordered, 'cyclic' => $remaining];
            }

            sort($free);
            array_push($ordered, ...$free);
            $remaining = array_values(array_diff($remaining, $free));
        }

        return ['ordered' => $ordered, 'cyclic' => []];
    }

    /**
     * Every namespace in the graph — those that reference something, and those referenced.
     *
     * @return list<string>
     */
    private function nodes(): array
    {
        $nodes = $this->arrows;

        foreach ($this->arrows as $targets) {
            foreach (array_keys($targets) as $target) {
                $nodes[$target] ??= [];
            }
        }

        return array_keys($nodes);
    }
}
