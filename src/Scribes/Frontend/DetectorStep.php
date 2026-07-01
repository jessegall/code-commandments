<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes\Frontend;

use JesseGall\CodeCommandments\Cli\Scope\Scope;
use JesseGall\CodeCommandments\Detector as RootDetector;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Scribes\DetectorStep as BaseDetectorStep;
use JesseGall\CodeCommandments\Vue\Codebase as VueCodebase;
use JesseGall\CodeCommandments\Vue\ComponentGraph;
use JesseGall\CodeCommandments\Vue\ComponentLibrary;
use JesseGall\CodeCommandments\Frontend\Detector;
use JesseGall\CodeCommandments\Vue\PropTypes;
use JesseGall\CodeCommandments\WorkingCopy;

/**
 * Runs a {@see Repentable} FRONTEND detector's scribe over the Vue components, fed the
 * detector's own findings. {@see extractsComponents} tells the chain whether this step
 * CREATES files (an extraction) so the default order runs those last, after the in-place
 * fixers.
 */
final class DetectorStep extends BaseDetectorStep
{
    public function __construct(private readonly Detector&Repentable $detector) {}

    public function run(string $path, Scope $scope, WorkingCopy $overlay = new WorkingCopy()): array
    {
        $codebase = VueCodebase::scan($path, $overlay);
        $scribe = $this->scribe();

        // An extractor reuses an existing component before creating a duplicate, and traces a
        // forwarded prop up the render tree (the graph) when the source can't type it locally.
        if ($scribe instanceof ExtractComponentScribe) {
            $scribe->withLibrary(ComponentLibrary::from($codebase));
            $scribe->withPropTypes(new PropTypes(ComponentGraph::of($codebase)));
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

    protected function repentable(): RootDetector&Repentable
    {
        return $this->detector;
    }
}
