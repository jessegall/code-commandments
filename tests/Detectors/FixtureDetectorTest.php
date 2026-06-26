<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\ArchaeologyCommentDetector;
use JesseGall\CodeCommandments\Detectors\Backend\ArrayBagDetector;
use JesseGall\CodeCommandments\Detectors\Backend\ArrayReturnBagDetector;
use JesseGall\CodeCommandments\Detectors\Backend\ConfigReadDetector;
use JesseGall\CodeCommandments\Detectors\Backend\ContainerReachDetector;
use JesseGall\CodeCommandments\Detectors\Backend\GenericExceptionDetector;
use JesseGall\CodeCommandments\Detectors\Backend\InlineThrowDetector;
use JesseGall\CodeCommandments\Detectors\Backend\NewDataObjectDetector;
use JesseGall\CodeCommandments\Detectors\Backend\RawDecodedArrayReturnDetector;
use JesseGall\CodeCommandments\Detectors\Backend\RawRequestInputDetector;
use JesseGall\CodeCommandments\Testing\FixtureVerifier;
use PHPUnit\Framework\TestCase;

/**
 * The integration layer: every detector is run over the whole Shop fixture and
 * checked against its `#[Sinful]` markers. A detector passes when it flags every
 * marked sin (no misses) and nothing else (no unexpected) — the unmarked code is
 * the false-positive guard.
 */
final class FixtureDetectorTest extends TestCase
{
    public function test_detectors_match_the_fixture_markers(): void
    {
        $codebase = Codebase::scan(__DIR__ . '/../Fixtures/shop');

        $detectors = [
            new RawRequestInputDetector,
            new ContainerReachDetector,
            new GenericExceptionDetector,
            new InlineThrowDetector,
            new ArchaeologyCommentDetector,
            new ConfigReadDetector,
            new NewDataObjectDetector,
            new ArrayBagDetector,
            new ArrayReturnBagDetector,
            new RawDecodedArrayReturnDetector,
        ];

        foreach (new FixtureVerifier()->verify($codebase, $detectors) as $result) {
            $this->assertSame([], $result->missed, "{$result->detector} missed marked sins");
            $this->assertSame([], $result->unexpected, "{$result->detector} flagged unmarked code (a false positive, or an unmarked #[Sinful])");
        }
    }
}
