<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\MatchDefaultReturnsNullDetector;
use PHPUnit\Framework\TestCase;

final class MatchDefaultReturnsNullDetectorTest extends TestCase
{
    public function test_flags_a_match_default_returning_absence_only(): void
    {
        $code = <<<'PHP'
        <?php
        class S {
            public function a($x) { return match ($x) { 1 => 'one', default => null }; }
            public function b($x) { return match ($x) { 1 => 'one', default => [] }; }
            public function c($x) { return match ($x) { 1 => 'one', default => 'other' }; }
            public function d($x) { return match ($x) { 1 => 'one', default => throw new \RuntimeException }; }
        }
        PHP;

        $hits = (new MatchDefaultReturnsNullDetector)->find(Codebase::fromString($code));
        $scopes = array_map(static fn ($m): string => $m->scope(), $hits);
        sort($scopes);

        // a (null) + b ([]) — not c (real value), not d (throws).
        $this->assertSame(['S::a', 'S::b'], $scopes);
    }
}
