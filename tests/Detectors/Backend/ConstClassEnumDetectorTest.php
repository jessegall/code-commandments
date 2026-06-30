<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\ConstClassEnumDetector;
use PHPUnit\Framework\TestCase;

final class ConstClassEnumDetectorTest extends TestCase
{
    public function test_flags_a_scalar_const_class_only(): void
    {
        $code = <<<'PHP'
        <?php
        class Statuses {
            const PENDING = 'pending';
            const PAID = 'paid';
            const SHIPPED = 'shipped';
        }
        class HasBehaviour {
            const PENDING = 'pending';
            const PAID = 'paid';
            public function label(): string { return self::PENDING; }
        }
        class Config {
            const TIMEOUT = 30;
            public int $retries = 3;
        }
        class OneConst {
            const VERSION = '1.0';
        }
        PHP;

        $hits = (new ConstClassEnumDetector)->find(Codebase::fromString($code));
        $names = array_map(static fn ($m): string => $m->enclosingClassName() ?? '?', $hits);

        // Only the pure multi-const scalar class. Not the one with a method, not
        // the one with a property, not the single-const class.
        $this->assertSame(['Statuses'], $names);
    }
}
