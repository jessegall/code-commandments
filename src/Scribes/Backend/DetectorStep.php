<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Cli\Scope\Scope;
use JesseGall\CodeCommandments\Detector as RootDetector;
use JesseGall\CodeCommandments\Detectors\Detector;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Scribes\DetectorStep as BaseDetectorStep;
use JesseGall\CodeCommandments\Scribes\NeedsCodebase;

/**
 * Runs a {@see Repentable} BACKEND detector's scribe over the PHP AST, fed the detector's
 * own findings ({@see \JesseGall\CodeCommandments\Ast\NodeMatch}es, each exposing a `span()`).
 * The backend twin of {@see \JesseGall\CodeCommandments\Scribes\Frontend\DetectorStep} —
 * same shape, over the PHP {@see Codebase} instead of the Vue one.
 */
final class DetectorStep extends BaseDetectorStep
{
    public function __construct(private readonly Detector&Repentable $detector) {}

    public function run(string $path, Scope $scope): array
    {
        $codebase = Codebase::scan($path);
        $scribe = $this->scribe();

        // A scribe that needs whole-program context (e.g. to resolve a target class's
        // shape) gets the scanned codebase before it rewrites.
        if ($scribe instanceof NeedsCodebase) {
            $scribe->withCodebase($codebase);
        }

        // Honour the scope: only repent sins in files the scope includes.
        $findings = array_values(array_filter(
            $this->detector->find($codebase),
            static fn ($match): bool => $scope->includes($match->file->path),
        ));

        return $scribe->rewrite($findings);
    }

    protected function repentable(): RootDetector&Repentable
    {
        return $this->detector;
    }
}
