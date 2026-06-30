<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use JesseGall\CodeCommandments\Codebase;

/**
 * Reduces each detector's findings over a fixture to DIVERSITY SCENARIOS — a
 * `{file, source}` per finding, where "source" is the enclosing unit that counts as
 * one scenario (a PHP class, a Vue component). The symmetric twin of
 * {@see MarkerVerifier}: the backend's {@see ClassScenarioResolver} and frontend's
 * {@see ComponentScenarioResolver} produce the same shape, so the shared fixture
 * harness feeds either into {@see Diversity} without caring which engine it is.
 */
interface ScenarioResolver
{
    /**
     * @param  list<\JesseGall\CodeCommandments\Detector>  $detectors
     * @return array<string, list<array{file: string, source: string}>>  detector => scenarios
     */
    public function resolve(Codebase $codebase, array $detectors): array;
}
