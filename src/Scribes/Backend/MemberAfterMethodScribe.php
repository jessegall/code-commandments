<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes\Backend;

use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Scribes\RepentScribe;
use JesseGall\CodeCommandments\Scribes\Writer;

/**
 * Fixes {@see \JesseGall\CodeCommandments\Detectors\Backend\MemberAfterMethodDetector}: every trait use,
 * constant, property and enum case found below a method moves to the head of its class, just above the
 * first method, carrying its docblock and attributes. The strays of one class travel together as a
 * block, so a run split around a method keeps its written order. A relocation of the original bytes —
 * nothing is reprinted, so formatting and comments survive intact.
 */
final class MemberAfterMethodScribe extends RepentScribe
{
    public function rewrite(array $findings): array
    {
        $draft = $this->draft([]);

        foreach ($this->perClass($findings) as $strays) {
            $anchor = $strays[0]->firstMethodOfItsClass();

            if ($anchor === null) {
                continue; // No method to stand above — nothing this scribe can move.
            }

            Writer::for($draft, $strays[0])
                ->moveBefore(array_map(static fn (NodeMatch $m): \PhpParser\Node => $m->node, $strays), $anchor);
        }

        return $draft->rewrites();
    }

    /**
     * The findings grouped by the class they sit in, each group in source order — the query hands them
     * back bucketed by node kind (every property, then every constant), which is not the order they
     * must be re-inserted in.
     *
     * @param  list<object>  $findings
     * @return list<list<NodeMatch>>
     */
    private function perClass(array $findings): array
    {
        $groups = [];

        foreach ($findings as $finding) {
            if ($finding instanceof NodeMatch && $finding->node !== null) {
                $groups[$finding->file->path . '::' . ($finding->enclosingClassName() ?? '')][] = $finding;
            }
        }

        foreach ($groups as &$group) {
            usort($group, static fn (NodeMatch $a, NodeMatch $b): int => $a->node->getStartFilePos() <=> $b->node->getStartFilePos());
        }

        return array_values($groups);
    }
}
