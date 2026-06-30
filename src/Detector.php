<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

use JesseGall\CodeCommandments\Sins\Sin;

/**
 * The root of every Sin Detector, backend or frontend. It carries the one thing
 * both engines share — the {@see Sin} a finding points at — while each engine's
 * `Detector` sub-interface adds the `find()` over its own codebase (PHP AST nodes
 * for {@see Detectors\Detector}, Vue elements for {@see Vue\Detector}).
 *
 * A detector finds ONE sin and names it; the sin carries the skill that teaches the
 * fix and the description the docs are generated from. The detector itself has no fix
 * logic (that's a {@see Scribes\Scribe}, named via {@see Detectors\Repentable}).
 */
interface Detector
{
    /**
     * The sin this detector finds — its name (for `--sin=`), the skill that teaches
     * the fix, and the one-line description the roadmap/skill docs project from.
     */
    public function sin(): Sin;
}
