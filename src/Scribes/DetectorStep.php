<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes;

use JesseGall\CodeCommandments\Detector;
use JesseGall\CodeCommandments\Detectors\Repentable;

/**
 * A chain step that runs a {@see Repentable} detector's scribe over a codebase — fed the
 * detector's OWN findings, never re-scanning. Engine-agnostic: the {@see Backend\DetectorStep}
 * runs over the PHP AST and the {@see Frontend\DetectorStep} over the Vue components, but
 * both resolve the scribe, scope the findings, and call {@see RepentScribe::rewrite} the
 * same way — that shared machinery lives here.
 *
 * Each concrete step holds its engine's narrowly-typed detector and implements {@see run}
 * (the scan differs per engine) plus {@see repentable} (the engine-neutral handle this base
 * uses for `name()`/`--sin` matching/scribe resolution).
 */
abstract class DetectorStep implements ScribeStep
{
    /**
     * The detector behind this step, as the engine-neutral root {@see Detector} (carrying
     * the {@see Repentable} contract) — enough for the shared name/sin/scribe logic.
     */
    abstract protected function repentable(): Detector&Repentable;

    public function name(): string
    {
        $parts = explode('\\', $this->repentable()::class);

        return end($parts);
    }

    /**
     * Most fixers rewrite in place; a frontend extractor overrides this to run last.
     */
    public function extractsComponents(): bool
    {
        return false;
    }

    /**
     * Does the detector behind this step find the `--sin=<query>` named? Lets `repent
     * --sin=redundant-else` scope to a step the same lenient way `judge --sin=` does.
     */
    public function matchesSin(string $query): bool
    {
        return $this->repentable()->sin()->matches($query);
    }

    /**
     * Resolve the {@see RepentScribe} the detector names — a class-string, a configured
     * instance, or a callable factory (the three {@see Repentable::scribe} forms).
     */
    protected function scribe(): RepentScribe
    {
        $spec = $this->repentable()->scribe();

        if (is_string($spec)) {
            return new $spec();
        }

        return $spec instanceof RepentScribe ? $spec : $spec();
    }
}
