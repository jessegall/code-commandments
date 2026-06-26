<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\SwallowCatchDetector;
use PHPUnit\Framework\TestCase;

final class SwallowCatchDetectorTest extends TestCase
{
    public function test_flags_catches_that_swallow_into_absence(): void
    {
        $code = <<<'PHP'
        <?php
        class S {
            public function a() { try { x(); } catch (\Throwable $e) {} }
            public function b() { try { x(); } catch (\Throwable $e) { return null; } }
            public function c() { try { x(); } catch (\Throwable $e) { return []; } }
            public function d() { try { x(); } catch (\Throwable $e) { return false; } }
            public function ok1() { try { x(); } catch (\Throwable $e) { $this->log($e); throw $e; } }
            public function ok2() { try { x(); } catch (\Throwable $e) { report($e); return null; } }
        }
        PHP;

        $hits = (new SwallowCatchDetector)->find(Codebase::fromString($code));
        $scopes = array_map(static fn ($m): string => $m->scope(), $hits);
        sort($scopes);

        $this->assertSame(['S::a', 'S::b', 'S::c', 'S::d'], $scopes);
    }
}
