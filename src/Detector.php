<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

/**
 * The root of every Sin Detector, backend or frontend. It carries the one thing
 * both engines share — the skill a finding points at — while each engine's
 * `Detector` sub-interface adds the `find()` over its own codebase (PHP AST nodes
 * for {@see Detectors\Detector}, Vue elements for {@see Vue\Detector}).
 *
 * A detector finds ONE sin and names the skill that teaches the fix; it carries no
 * fix logic (that's a {@see Cli\Rewriting\Scribe}, named via {@see Detectors\Repentable}).
 */
interface Detector
{
    /**
     * The skill slug a finding points the agent at ("read this").
     */
    public function skill(): string;
}
