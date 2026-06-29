<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors;

/**
 * A detector whose sin can be REPENTED — auto-fixed at the source. Implemented
 * alongside a backend {@see Detector} or a frontend {@see \JesseGall\CodeCommandments\Vue\Detector},
 * it names the {@see \JesseGall\CodeCommandments\Cli\Rewriting\Scribe} that rewrites
 * the sin away.
 *
 * `judge` never fixes on its own — it only reports. The existing `scribe` command
 * is what runs these (with its `--dry-run` to preview): `Repentable` is the bridge
 * that tells the scribe ecosystem which sins it can absolve.
 */
interface Repentable
{
    /**
     * The Scribe class that auto-fixes this detector's sin.
     *
     * @return class-string
     */
    public function scribe(): string;
}
