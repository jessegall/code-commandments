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

    /**
     * @var array<string, true>  every first-party namespace seen, including ones whose references
     *                           all stayed inside themselves and so drew no arrow
     */
    private array $namespaces = [];

    private function __construct(Codebase $codebase)
    {
        foreach ($codebase->whereClassReference()->get() as $reference) {
            $from = $reference->namespaceName();
            $target = $reference->referencedClassName();

            if ($from === null || $codebase->declarationMatch($target) === null) {
                continue;
            }

            $to = ClassName::namespace((string) $target);
            $this->namespaces[$from] = true;

            if ($to !== '') {
                $this->namespaces[$to] = true;
            }

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
     * A layer declaration that HOLDS this codebase where it stands — every namespace as a layer,
     * each naming what it already reaches. Green by construction: every reference in the tree
     * either stays inside a layer or is named in that layer's `mayUse`, so a freshly written
     * declaration never reports a breach on the next judge.
     *
     * Only MAXIMAL namespaces are emitted — never one nested inside another. Declaring both a
     * namespace and its child would split them into two layers and cancel the containment that
     * makes their ordinary internal references legal, turning `App\Ai` ↔ `App\Ai\Contracts` into a
     * pair of illegal arrows.
     *
     * @return array<string, list<string>>  layer => the layers it may use
     */
    public function currentShape(): array
    {
        $layers = self::maximal($this->nodes());
        $shape = array_fill_keys($layers, []);

        foreach ($this->arrows as $from => $targets) {
            $fromLayer = self::containing($layers, $from);

            foreach (array_keys($targets) as $to) {
                $toLayer = self::containing($layers, $to);

                if ($fromLayer === null || $toLayer === null || $fromLayer === $toLayer) {
                    continue;
                }

                $shape[$fromLayer][$toLayer] = true;
            }
        }

        return $this->inDependencyOrder(array_map(
            static function (array $uses): array {
                $names = array_keys($uses);
                sort($names);

                return $names;
            },
            $shape,
        ));
    }

    /**
     * The bottom of {@see currentShape}: the emitted layers that reach nothing else you own. A
     * smaller declaration to start from, and one that can never be wrong — though it constrains
     * only what it covers, since a layer may always use a namespace nobody declared.
     *
     * @return array<string, list<string>>
     */
    public function floorShape(): array
    {
        return array_filter($this->currentShape(), static fn (array $uses): bool => $uses === []);
    }

    /**
     * Drop every namespace that sits inside another in the same set, leaving only the outermost —
     * the set that can be declared as layers without any of them swallowing another.
     *
     * @param  list<string>  $namespaces
     * @return list<string>
     */
    private static function maximal(array $namespaces): array
    {
        return array_values(array_filter(
            $namespaces,
            static fn (string $candidate): bool => ! array_any(
                $namespaces,
                static fn (string $other): bool => $other !== $candidate && ClassName::within($candidate, $other),
            ),
        ));
    }

    /**
     * The emitted layer $namespace belongs to, or null when none covers it.
     *
     * @param  list<string>  $layers
     */
    private static function containing(array $layers, string $namespace): ?string
    {
        foreach ($layers as $layer) {
            if (ClassName::within($namespace, $layer)) {
                return $layer;
            }
        }

        return null;
    }

    /**
     * Re-key a shape so a layer comes after the ones it uses — the declaration then reads as the
     * stack, bottom-up. Layers caught in a cycle keep their alphabetical order.
     *
     * @param  array<string, list<string>>  $shape
     * @return array<string, list<string>>
     */
    private function inDependencyOrder(array $shape): array
    {
        $order = self::topological($shape);
        $ordered = [];

        foreach ([...$order['ordered'], ...$order['cyclic']] as $layer) {
            $ordered[$layer] = $shape[$layer];
        }

        return $ordered;
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
        // Every namespace, including those only ever referenced — they are the bottom of the stack,
        // and an order that left them out would describe the wrong shape.
        return self::topological(array_merge(array_fill_keys($this->nodes(), []), $this->edges()));
    }

    /**
     * Sort an edge map so a node comes after everything it points at, and report the nodes no order
     * could place because they sit in a cycle. Ties break on the name, so the same input always
     * yields the same answer.
     *
     * @param  array<string, list<string>>  $edges
     * @return array{ordered: list<string>, cyclic: list<string>}
     */
    private static function topological(array $edges): array
    {
        $remaining = array_keys($edges);
        $ordered = [];

        while ($remaining !== []) {
            $free = array_values(array_filter(
                $remaining,
                static fn (string $node): bool => array_all(
                    $edges[$node] ?? [],
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
        return array_keys($this->namespaces);
    }
}
