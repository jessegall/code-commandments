<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\Docstring;
use JesseGall\CodeCommandments\Py\NodeMatch;
use JesseGall\CodeCommandments\Python\Detector;

/**
 * A rule about the prose written for a statement — the run of comments above it, and the docstring it opens
 * with — judged a text at a time, its Sphinx version notes set aside.
 */
abstract class ProseRule implements Detector
{
    /**
     * Does $text commit the sin this rule is about?
     */
    abstract protected function isSinful(string $text): bool;

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereStatement()
            ->where(fn (NodeMatch $match): bool => array_any($match->prose(), fn (string $text) => $this->isSinful(Docstring::withoutVersionNotes($text))))
            ->get();
    }
}
