<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes;

use JesseGall\CodeCommandments\Cli\Scope\Scope;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Scribes\Frontend\ExtractComponentScribe;
use JesseGall\CodeCommandments\Vue\Codebase as VueCodebase;
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
        return $this->scribe()->rewrite($this->detector->find(VueCodebase::scan($path)));
    }

    public function extractsComponents(): bool
    {
        return $this->scribe() instanceof ExtractComponentScribe;
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
