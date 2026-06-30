<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Testing;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Detector;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Testing\SinfulMarkerVerifier;
use PHPUnit\Framework\TestCase;

/**
 * A stub detector that flags every `->input()` call (no receiver filtering),
 * used to prove the verifier's bookkeeping.
 */
final class ProbeDetector implements Detector
{
    public function sin(): Sin
    {
        return new class extends Sin {
            public function __construct()
            {
                parent::__construct(name: 'probe', skill: 'probe', description: 'probe', rule: 'probe');
            }
        };
    }

    public function find(Codebase $codebase): array
    {
        return $codebase->whereMethod('input')->get();
    }
}

final class SinfulMarkerVerifierTest extends TestCase
{
    public function test_reports_missed_marks_and_unexpected_findings(): void
    {
        $code = <<<'PHP'
        <?php
        namespace T;
        use JesseGall\CodeCommandments\Testing\Sinful;
        class A {
            #[Sinful(\JesseGall\CodeCommandments\Tests\Testing\ProbeDetector::class)]
            public function marked($r) { $r->input('x'); }
            public function unmarked($r) { $r->input('y'); }
            #[Sinful(\JesseGall\CodeCommandments\Tests\Testing\ProbeDetector::class)]
            public function blank($r) { }
        }
        PHP;

        $result = (new SinfulMarkerVerifier)->verify(Codebase::fromString($code), [new ProbeDetector])[0];

        // marked + flagged -> ok; unmarked + flagged -> unexpected; marked + not flagged -> missed
        $this->assertCount(1, $result->unexpected, 'the unmarked ->input() must be reported');
        $this->assertCount(1, $result->missed, 'the marked-but-clean method must be reported');
        $this->assertFalse($result->passed());
    }
}
