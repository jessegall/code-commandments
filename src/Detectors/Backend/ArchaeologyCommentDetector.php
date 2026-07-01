<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\ArchaeologyComment;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;

/**
 * A comment that narrates the code's past — `// formerly...`, `// used to be
 * a...`, `// refactored into...`, `// no longer an X`. Git holds the history; the
 * comment should describe the present code. Points at the documentation skill.
 *
 * The trigger words are only the UNAMBIGUOUS archaeology markers: "previously",
 * "used to <do>", bare "no longer", "renamed", "moved" all carry legitimate
 * present-tense/domain readings ("previously bound", "used to scope", "no longer
 * exists on disk"), so they are excluded to keep the detector false-positive-free.
 */
final class ArchaeologyCommentDetector implements Detector
{
    private const string PATTERN = '/'
        . '\b(?:formerly|refactored|renamed from|was extracted|ported from|is retired)\b'
        . '|\bused to (?:be|live|have|hold|contain|return|exist|sit|post|fire)\b'
        // "no longer an X" / "no longer <does>" — but NOT runtime state (exists/matches/contains/fits)
        . '|\bno longer (?:an?|does|reads|runs|fires|handles|unwraps|posts|matters)\b'
        . '|\bnow lives (?:in|inside)\b'
        // clause-initial "previously this/every/we/it…" narration — excludes runtime "previously bound"
        . '|\bpreviously\s+(?:this|every|we|it)\b'
        . '|\bequivalent of the old\b'
        . '/i';

    public function sin(): Sin
    {
        return new ArchaeologyComment();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase->whereComment(self::PATTERN)->get();
    }
}
