<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\NamespaceGraph;
use JesseGall\CodeCommandments\Cs\Node;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Sins\CSharp\DerivedArgument;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\WholeTree;

/**
 * A call that hands over a projection of a value beside the value itself, or the value in pieces — the twin of
 * the backend's and Python's derived-argument rules. The evidence is one name reached twice in one call, so the
 * method provably holds what the derivation needs; and it is a question about the PARAMETER, so every call
 * filling it must derive the same member. A method also handed out as a delegate has callers no call site shows,
 * and is left alone.
 */
final class DerivedArgumentDetector implements Detector, WholeTree
{
    /**
     * How many pieces of one value, with the value itself nowhere in the call, say the method is reassembling
     * it. Two is a generic helper filling a title and a subtitle; three is an object in pieces.
     */
    private const int REASSEMBLED = 3;

    public function sin(): Sin
    {
        return new DerivedArgument();
    }

    public function find(Codebase $codebase): array
    {
        $graph = new NamespaceGraph($codebase);
        $redundant = [];
        $supplied = [];
        $paths = [];
        $unresolved = [];

        foreach ($codebase->whereCall()->get() as $call) {
            if ($call->node->target === null) {
                $unresolved[(string) $call->node->calledName()] = true;

                continue;
            }

            if (! $call->node->passesByPosition() || ! $codebase->reachesOwnSignature($call->node) || $codebase->isHandedOut((string) $call->node->calledName())) {
                continue;
            }

            $slot = $call->node->target->symbol();

            foreach (array_keys($call->node->arguments()) as $position) {
                $supplied["{$slot}#{$position}"] = ($supplied["{$slot}#{$position}"] ?? 0) + 1;
            }

            foreach ($this->redundantPositions($call, $codebase, $graph) as $position) {
                $redundant["{$slot}#{$position}"][] = $call;
                $paths["{$slot}#{$position}"][$call->node->arguments()[$position]->projectionPath()] = true;
            }
        }

        $findings = [];

        foreach ($redundant as $slot => $calls) {
            if (count($calls) === $supplied[$slot] && count($paths[$slot]) === 1 && ! isset($unresolved[(string) $calls[0]->node->calledName()])) {
                foreach ($calls as $call) {
                    $findings[spl_object_id($call)] = $call;
                }
            }
        }

        return array_values($findings);
    }

    /**
     * The positions this call fills with a projection of a name it reaches twice — beside the name itself, or as
     * one of {@see REASSEMBLED} pieces of it.
     *
     * @return list<int>
     */
    private function redundantPositions(NodeMatch $call, Codebase $codebase, NamespaceGraph $graph): array
    {
        if (in_array('global::System.Object', $call->node->target->parameters ?? [], true)) {
            return []; // a slot told nothing about what it holds cannot derive anything from it
        }

        $receiver = $call->node->receiverName();
        $whole = [];
        $pieces = [];

        foreach ($call->node->arguments() as $position => $argument) {
            if ($argument->is('IdentifierName') && $argument->name !== $receiver) {
                $whole[(string) $argument->name] = true;

                continue;
            }

            $root = $argument->projectionRoot();

            if ($root !== null && $root->name !== $receiver && $call->node->fillsScalarAt($position)) {
                $pieces[(string) $root->name][$position] = $root;
            }
        }

        $redundant = array_filter($pieces, fn (array $roots, string $name): bool => isset($whole[$name])
            || (count($roots) >= self::REASSEMBLED && $this->couldTakeWhole($call, reset($roots), $codebase, $graph)), ARRAY_FILTER_USE_BOTH);

        return array_merge([], ...array_map(array_keys(...), array_values($redundant)));
    }

    /**
     * Could the method take whole the value $root names? Only when the codebase declares its type — the fix names
     * a type — and only when taking it opens no namespace cycle: a mapper between two layers is the one place
     * allowed to see both, so taking the pieces is its whole job.
     */
    private function couldTakeWhole(NodeMatch $call, Node $root, Codebase $codebase, NamespaceGraph $graph): bool
    {
        $type = rtrim((string) $root->type?->name, '?');

        return $codebase->declaresType($type) && ! $graph->wouldCloseACycle((string) $call->node->target?->type, $type);
    }
}
