<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes;

use JesseGall\CodeCommandments\Cli\Scope\Scope;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Scribes\Frontend\ExtractComponentScribe;
use JesseGall\CodeCommandments\Vue\Codebase as VueCodebase;
use JesseGall\CodeCommandments\Vue\ComponentLibrary;
use JesseGall\CodeCommandments\Vue\Detector;

/**
 * A chain step that runs a {@see Repentable} frontend detector's scribe over the Vue
 * components — fed the detector's own findings. {@see extractsComponents} tells the
 * chain whether this step CREATES files (an extraction) so the default order can run
 * those last, after the in-place fixers.
 */
final class DetectorStep implements ScribeStep
{
    public function __construct(private readonly Detector&Repentable $detector) {}

    public function name(): string
    {
        $parts = explode('\\', $this->detector::class);

        return end($parts);
    }

    public function run(string $path, Scope $scope): array
    {
        $codebase = VueCodebase::scan($path);
        $scribe = $this->scribe();

        // An extractor reuses an existing component before creating a duplicate.
        if ($scribe instanceof ExtractComponentScribe) {
            $scribe->withLibrary(ComponentLibrary::from($codebase));
        }

        // Honour the scope (a `--repent=ID` checklist, a `--changes`/`--branch` set):
        // only repent sins in files the scope includes.
        $findings = array_values(array_filter(
            $this->detector->find($codebase),
            static fn ($match): bool => $scope->includes($match->file()),
        ));

        return $scribe->rewrite($findings);
    }

    public function extractsComponents(): bool
    {
        return $this->scribe() instanceof ExtractComponentScribe;
    }

    /**
     * Does the detector behind this step find the `--sin=<query>` named? Lets `repent
     * --sin=switch-case` scope to a step the same lenient way `judge --sin=` does.
     */
    public function matchesSin(string $query): bool
    {
        return $this->detector->sin()->matches($query);
    }

    private function scribe(): RepentScribe
    {
        $spec = $this->detector->scribe();

        if (is_string($spec)) {
            return new $spec();
        }

        return $spec instanceof RepentScribe ? $spec : $spec();
    }
}
