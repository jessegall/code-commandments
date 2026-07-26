<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes\Backend;

use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Ast\Support\ClassLayoutOrder;
use JesseGall\CodeCommandments\Scribes\RepentScribe;
use JesseGall\CodeCommandments\Scribes\Writer;

/**
 * Fixes {@see \JesseGall\CodeCommandments\Detectors\Backend\MemberOutOfOrderDetector}: the head of the
 * class is re-emitted in canonical order ({@see ClassLayoutOrder}) — one rewrite of the run, each
 * declaration kept as its original bytes. The sort is stable, so members of equal rank stay in the
 * order their author chose and only what is genuinely misplaced moves.
 */
final class MemberOutOfOrderScribe extends RepentScribe
{
    public function rewrite(array $findings): array
    {
        $draft = $this->draft([]);

        foreach ($this->oneFindingPerClass($findings) as $finding) {
            $class = $finding->enclosingClass();

            if ($class === null) {
                continue;
            }

            Writer::for($draft, $finding)->reorder(ClassLayoutOrder::headOf($class), ClassLayoutOrder::groupedHeadOf($class));
        }

        return $draft->rewrites();
    }

    /**
     * One finding per class — the whole head is rewritten in a single edit, so a class with three
     * misplaced members must not be reordered three times.
     *
     * @param  list<object>  $findings
     * @return list<NodeMatch>
     */
    private function oneFindingPerClass(array $findings): array
    {
        $byClass = [];

        foreach ($findings as $finding) {
            if ($finding instanceof NodeMatch) {
                $byClass[$finding->file->path . '::' . ($finding->enclosingClassName() ?? '')] ??= $finding;
            }
        }

        return array_values($byClass);
    }
}
