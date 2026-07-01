<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors;

/**
 * A detector whose sin can be REPENTED — auto-fixed at the source. Implemented
 * alongside a backend {@see Detector} or a frontend {@see \JesseGall\CodeCommandments\Frontend\Detector},
 * it names the Scribe that rewrites the sin away.
 *
 * `judge` never fixes on its own — it only reports. The existing `scribe` command
 * is what runs these (with its `--dry-run` to preview): `Repentable` is the bridge
 * that tells the scribe ecosystem which sins it can absolve.
 *
 * The named {@see \JesseGall\CodeCommandments\Scribes\RepentScribe} does NOT
 * re-scan — the runner feeds it THIS detector's findings (`find()`), so the scribe
 * starts from the exact matches the detector already located.
 *
 * The scribe can be given three ways — whichever reads cleanest for the detector:
 *   - a **class-string** for a plain, parameterless scribe (`SwitchCaseScribe::class`);
 *   - a **configured instance** — so two detectors can share one base scribe, each
 *     handing back its own tuned variant (e.g. both the duplicate-element and
 *     deep-reach detectors fix by EXTRACTING a component, so both return an
 *     `ExtractComponentScribe` set to their strategy);
 *   - a **callable factory** returning one of the above.
 */
interface Repentable
{
    /**
     * @return class-string<\JesseGall\CodeCommandments\Scribes\RepentScribe>|callable(): \JesseGall\CodeCommandments\Scribes\RepentScribe|\JesseGall\CodeCommandments\Scribes\RepentScribe
     */
    public function scribe(): string|callable|object;
}
