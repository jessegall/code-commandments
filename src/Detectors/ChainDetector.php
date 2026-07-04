<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Backend\Detector;

/**
 * A detector whose verdict depends on following a value through the whole program — the
 * {@see \JesseGall\CodeCommandments\Ast\ValueFlow} provenance graph, not a single hop. It IS a
 * {@see Detector} (implement THIS instead of `Detector`, not alongside it), and tagging earns a
 * fixture contract fit for a chain engine: its samples must show three findings whose PROVENANCE
 * differs — each crossing several files, along genuinely different routes (minor overlap only) AND
 * different in kind (assignment vs. call/return vs. field-write). {@see \JesseGall\CodeCommandments\Testing\FixtureTestCase}
 * reads all of that from {@see chainPath}.
 *
 * @see \JesseGall\CodeCommandments\Detectors\Backend\PhantomNullableDetector
 */
interface ChainDetector extends Detector
{
    /**
     * The deciding provenance chain of $finding, as ordered `edgeKind@file` steps — the value's route
     * from the field to the read that earns the verdict. Its distinct files are how DEEP it goes; its
     * edge kinds are IN WHAT KIND; two findings' step lists overlapping little is how DIVERSE they are.
     *
     * @return list<string>
     */
    public function chainPath(NodeMatch $finding, Codebase $codebase): array;
}
