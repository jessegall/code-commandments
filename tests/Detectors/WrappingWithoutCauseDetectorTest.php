<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\WrappingWithoutCauseDetector;
use PHPUnit\Framework\TestCase;

final class WrappingWithoutCauseDetectorTest extends TestCase
{
    public function test_flags_wrapping_that_drops_the_cause_only(): void
    {
        $code = <<<'PHP'
        <?php
        class S {
            public function a() { try { x(); } catch (\Throwable $e) { throw new DomainError('boom'); } }
            public function b() { try { x(); } catch (\Throwable $e) { throw new DomainError('boom', 0, $e); } }
            public function c() { throw new DomainError('boom'); }
        }
        PHP;

        $hits = (new WrappingWithoutCauseDetector)->find(Codebase::fromString($code));

        // a drops the cause. b chains it. c is not in a catch.
        $this->assertSame(['S::a'], array_map(static fn ($m): string => $m->scope(), $hits));
    }
}
